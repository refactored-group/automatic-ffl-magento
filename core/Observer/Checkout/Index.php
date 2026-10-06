<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Observer\Checkout;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Action\Action;

class Index implements ObserverInterface
{
    /**
     * @var Helper
     */
    private $helper;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    private $responseFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param Helper $helper
     */

    private $request;
    private $actionFlag;

    public function __construct(
        Helper $helper,
        ManagerInterface $messageManager,
        Session $session,
        UrlInterface $url,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        Request $request,
        CartRepositoryInterface $quoteRepository,
        ActionFlag $actionFlag
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->session = $session;
        $this->url = $url;
        $this->responseFactory = $responseFactory;
        $this->request = $request;
        $this->quoteRepository = $quoteRepository;
        $this->actionFlag = $actionFlag;
    }

    /**
     * When FFL is enabled, verifies if all items in the cart are to be shipped by FFL.
     * If the cart is mixed with non-FFL items, redirect the customer to the shopping cart controller
     * and display an error message
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $eventName = $observer->getEvent()->getName();

        if ($this->helper->isEnabled() && $eventName === 'controller_action_predispatch_checkout_index_index') {
            $route = $this->helper->getCheckoutEntryRoute();
            if ($route === 'state' && $this->helper->consumeCheckoutEntry()) {
                $route = $this->helper->getCheckoutRoute();
            }
            if ($route === 'state' || $route === 'multishipping') {
                $path = $route === 'state' ? 'autoffl/routing/index' : 'multishipping/checkout';
                if ($route === 'multishipping' && !$this->helper->isMultishippingCheckoutAvailable()) {
                    $path = 'checkout/cart';
                    // The cart observer owns the notice after this redirect.
                }
                $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
                return $observer->getControllerAction()->getResponse()->setRedirect($this->url->getUrl($path));
            }
            $this->resetFflCheckoutState();
        }

        if ($this->helper->isEnabled() && $this->helper->isMixedCart()) {
            if ($eventName === 'controller_action_predispatch_checkout_index_index') {
                if ($this->helper->isMultishippingCheckoutAvailable()) {
                    return $observer->getControllerAction()
                        ->getResponse()
                        ->setRedirect($this->url->getUrl('multishipping/checkout'));
                } elseif (!$this->helper->shipNonGunItems()) {
                    return $observer->getControllerAction()
                        ->getResponse()
                        ->setRedirect($this->url->getUrl('checkout/cart/index'));
                }
            } elseif ($eventName === 'controller_action_predispatch_checkout_cart_index') {
                if ($this->helper->isMultishippingCheckoutAvailable()) {
                    $message = __('Some items in your order must ship to an FFL dealer.');
                } elseif (!$this->helper->shipNonGunItems()) {
                    $message = __('Some of your items require shipment to an FFL dealer. You will need to order them separately.');
                } elseif ($this->helper->shipNonGunItems()) {
                    $message  = __('Your cart has items that need to be shipped to a Dealer. '
                        . "All items will be shipped together. You'll be requested to select a Dealer on the next step.");
                }
            } elseif ($eventName === 'sales_order_place_before' && !$this->helper->shipNonGunItems()) {
                $message  = __('Your cart has items that need to be shipped to a Dealer. '
                    . 'You can not perform a regular checkout with a mixed cart. '
                    . 'Please, use the Multi-Shipping Checkout option.');
                $this->messageManager->addErrorMessage($message);
                $observer->getControllerAction()
                    ->getResponse()
                    ->setRedirect($this->url->getUrl('checkout/cart/index'));

                return;
            }

            if (!empty($message)) {
                $this->messageManager->addUniqueMessages([
                    $this->messageManager->createMessage(MessageInterface::TYPE_ERROR)->setText($message)
                ]);
            }
        }
    }

    /**
     * Clear stale FFL shipping state so every regular checkout page load requires
     * a fresh dealer selection.
     *
     * @return void
     */
    private function resetFflCheckoutState()
    {
        $quote = $this->session->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        $hadDealer = (bool) ($quote->getFflLicense() || $quote->getFflDealerData());
        if (!$this->helper->isFfl()) {
            $hasConditionalAmmo = $this->helper->hasConditionalAmmo();
            $clearRoutingState = !$hasConditionalAmmo && $quote->getFflRoutingState();
            if ($clearRoutingState) {
                $quote->setFflRoutingState(null);
            }
            if (!$hadDealer) {
                if ($clearRoutingState) {
                    $this->quoteRepository->save($quote);
                }
                return;
            }
            // Removing the last dealer-required item must also remove its
            // delivery address and rates before native checkout resolves them.
        }

        $quote->setFflLicense(null);
        $quote->setFflDealerData(null);
        $quote->setTotalsCollectedFlag(false);

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress) {
            $shippingAddress->setFirstname(null);
            $shippingAddress->setLastname(null);
            $shippingAddress->setCompany(null);
            $shippingAddress->setStreet([]);
            $shippingAddress->setCity(null);
            $shippingAddress->setCountryId(null);
            $shippingAddress->setRegion(null);
            $shippingAddress->setRegionId(null);
            $shippingAddress->setPostcode(null);
            $shippingAddress->setTelephone(null);
            $shippingAddress->setShippingMethod(null);
            $shippingAddress->setCollectShippingRates(true);

            if (method_exists($shippingAddress, 'removeAllShippingRates')) {
                $shippingAddress->removeAllShippingRates();
            }

            // QuoteRepository also saves the cached shipping assignment.
            // Its old method cannot be applied to the cleared dealer address.
            $extensionAttributes = $quote->getExtensionAttributes();
            foreach ($extensionAttributes ? ($extensionAttributes->getShippingAssignments() ?: []) : [] as $assignment) {
                $assignment->getShipping()->setAddress($shippingAddress)->setMethod(null);
            }
        }

        $this->quoteRepository->save($quote);
    }
}
