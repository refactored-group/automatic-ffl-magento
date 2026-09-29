<?php

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Observer\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\Session as CheckoutSession;
use RefactoredGroup\AutoFflCore\Model\OrderMetadata;
use RefactoredGroup\AutoFflCore\Model\QuoteAnalysis;
use RefactoredGroup\AutoFflCore\Helper\Data as AutoFflHelper;

class MultishippingCreateOrdersSingle implements ObserverInterface
{
    protected $customerSession;
    private $checkoutSession;
    private $metadata;
    private $analysis;
    private $helper;

    public function __construct(
        Session $customerSession,
        CheckoutSession $checkoutSession,
        OrderMetadata $metadata,
        QuoteAnalysis $analysis,
        AutoFflHelper $helper
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->metadata = $metadata;
        $this->analysis = $analysis;
        $this->helper = $helper;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $address = $observer->getData('address');
        $quote = $observer->getData('quote') ?: $this->checkoutSession->getQuote();
        $data = json_decode((string) $quote->getFflDealerData(), true);
        $addressId = (string) $address->getCustomerAddressId();
        $snapshot = is_array($data) ? ($data['addresses'][$addressId] ?? null) : null;
        $routingState = is_array($snapshot)
            ? ($snapshot['routingState'] ?? '')
            : $this->helper->getAddressState($address);
        $required = [];
        foreach ($this->analysis->analyze($quote, $routingState)['required'] as $item) {
            $required[(int) $item->getId()] = true;
        }
        $containsFfl = false;
        foreach ($order->getAllVisibleItems() as $item) {
            if (isset($required[(int) $item->getQuoteItemId()])) {
                $containsFfl = true;
                break;
            }
        }
        if (!$containsFfl) {
            return;
        }
        if (is_array($snapshot) && isset($snapshot['license'])) {
            $this->metadata->apply($order, $snapshot);
            return;
        }
        $fflLicense = $this->customerSession->getData('ffl_license_'.$address->getCustomerAddressId());

        if(!is_null($fflLicense)) {
            $order->setFflLicense($fflLicense);
        }
    }
}
