<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout;

use Closure;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use RefactoredGroup\AutoFflCore\Model\RecipientName;
use RefactoredGroup\AutoFflCheckoutMultiShipping\Model\DealerGrouping;
use RefactoredGroup\AutoFflCheckoutMultiShipping\Model\Recipient;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;

class AddressesPost
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Context
     */
    private $context;
    private $addresses;
    private $multishipping;
    private $formKeyValidator;
    private $logger;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper,
        Context $context,
        AddressRepositoryInterface $addresses,
        Multishipping $multishipping,
        Validator $formKeyValidator,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->context = $context;
        $this->addresses = $addresses;
        $this->multishipping = $multishipping;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
    }

    /**
     * Verify if a FFL has been chosen for each firewarm.
     * If  not, redirect back to the addresses page step.
     *
     * @param \Magento\Multishipping\Controller\Checkout\AddressesPost $subject
     * @param Closure $proceed
     * @return mixed
     */
    public function aroundExecute(\Magento\Multishipping\Controller\Checkout\AddressesPost $subject, Closure $proceed)
    {
        if ($this->context->getRequest()->getPost('ffl_async_routing')) {
            return $this->saveRoutingAsync();
        }
        if ($this->helper->hasFflItem()) {
            $items = $this->helper->getCustomerQuote()->getAllVisibleItems();

            if (count($items) > count((array) $this->context->getRequest()->getPost('ship'))) {
                $this->context->getMessageManager()->addErrorMessage(
                    __('Please, select a Licensed Firearm Dealer for the following product(s): ') .
                    $this->helper->getFflItemsNames()
                );
                return $this->backToAddresses();
            }
        }

        try {
            $quote = $this->helper->getCustomerQuote();
            if ($this->helper->isEnabled((int) $quote->getStoreId())) {
                $request = $this->context->getRequest();
                $ship = DealerGrouping::normalize($quote, (array) $request->getPost('ship'),
                    (array) $request->getPost('ffl_destination'), $this->helper, $this->addresses);
                $request->setPostValue('ship', $ship);
            }
            $this->checkDeliveryRouting();
            $this->saveRecipientNames();
        } catch (LocalizedException $error) {
            $this->context->getMessageManager()->addErrorMessage($error->getMessage());
            return $this->backToAddresses();
        }
        return $proceed();
    }

    private function saveRoutingAsync()
    {
        $result = $this->context->getResultFactory()->create(ResultFactory::TYPE_JSON);
        try {
            $request = $this->context->getRequest();
            if (!$request->isPost() || !$this->formKeyValidator->validate($request)) {
                return $result->setHttpResponseCode(400)->setData([
                    'success' => false, 'message' => __('Refresh checkout and try the destination again.')
                ]);
            }
            $quote = $this->helper->getCustomerQuote();
            $customerId = (int) $this->multishipping->getCustomerSession()->getCustomerId();
            if (!$quote->getId() || !$quote->getIsActive() || !$quote->getIsMultiShipping() ||
                !$customerId || (int) $quote->getCustomerId() !== $customerId ||
                !$this->helper->isEnabled((int) $quote->getStoreId())) {
                throw new LocalizedException(__('Refresh the active checkout before changing destinations.'));
            }
            $ship = (array) $request->getPost('ship');
            if (!$ship) {
                throw new LocalizedException(__('Select a shipping address for every item.'));
            }
            $ship = DealerGrouping::normalize($quote, $ship,
                (array) $request->getPost('ffl_destination'), $this->helper, $this->addresses);
            $this->saveRecipientNames();
            // Use the native address/quantity persistence without a redirect or storefront reload.
            $request->setPostValue('ship', $ship);
            $this->multishipping->setShippingItemsInformation($ship);
            return $result->setData(['success' => true]);
        } catch (LocalizedException $error) {
            return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => $error->getMessage()]);
        } catch (\Throwable $error) {
            $this->logger->error('Automatic FFL multishipping destination save failed.', ['exception' => $error]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false, 'message' => __('The shipping destination could not be saved. Please try again.')
            ]);
        }
    }

    private function checkDeliveryRouting(): void
    {
        $request = $this->context->getRequest();
        $quote = $this->helper->getCustomerQuote();
        if (!$this->helper->isEnabled((int) $quote->getStoreId()) || !$request->getParam('continue')) {
            return;
        }
        $snapshots = json_decode((string) $quote->getFflDealerData(), true);
        $needsDealer = [];
        foreach ((array) $request->getPost('ship') as $row) {
            foreach ((array) $row as $itemId => $selection) {
                $item = $quote->getItemById($itemId);
                if (!$item || $item->getProduct()->getIsVirtual() || (int) ($selection['qty'] ?? 0) <= 0) {
                    continue;
                }
                $id = (int) ($selection['address'] ?? 0);
                if (!$id) {
                    throw new LocalizedException(__('Select a shipping address for every item.'));
                }
                $address = $this->addresses->getById($id);
                if ((int) $address->getCustomerId() !== (int) $quote->getCustomerId()) {
                    throw new LocalizedException(__('The selected shipping address is unavailable.'));
                }
                $snapshot = $snapshots['addresses'][(string) $id] ?? null;
                $state = is_array($snapshot)
                    ? ($snapshot['routingStates'][$itemId] ?? $snapshot['routingState'] ?? '')
                    : $this->helper->getAddressState($address);
                if ($this->helper->isUnresolvedAmmoItem($item, $quote, $state)) {
                    throw new LocalizedException(__('Select a delivery state for ammunition.'));
                }
                if ($this->helper->isFflItem($item, $quote, $state) && empty($snapshot['license'])) {
                    $needsDealer[] = $item->getName();
                }
            }
        }
        if ($needsDealer) {
            // Save the intended ammo address so the next render offers its required dealer selector.
            $request->setParam('continue', 0);
            $this->context->getMessageManager()->addErrorMessage(
                __('Choose a licensed dealer for: %1.', implode(', ', array_unique($needsDealer)))
            );
        }
    }

    private function backToAddresses()
    {
        return $this->context->getResponse()->setRedirect($this->context->getUrl()->getUrl('multishipping/checkout/addresses'));
    }

    private function saveRecipientNames(): void
    {
        $quote = $this->helper->getCustomerQuote();
        if (!$this->helper->isEnabled((int) $quote->getStoreId())) {
            return;
        }
        $recipients = $this->context->getRequest()->getPost('ffl_recipient', []);
        if (!is_array($recipients)) {
            throw new LocalizedException(__('Enter a recipient name for dealer delivery.'));
        }
        Recipient::apply($quote, $recipients, $this->addresses);
    }
}
