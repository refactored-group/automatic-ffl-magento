<?php

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Observer\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session;

class MultishippingCreateOrdersSingle implements ObserverInterface
{
    protected $customerSession;

    public function __construct(
        Session $customerSession
    ) {
        $this->customerSession = $customerSession;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $address = $observer->getData('address');
        $fflLicense = $this->customerSession->getData('ffl_license_'.$address->getCustomerAddressId());
        $this->customerSession->unsetData('ffl_license_'.$address->getCustomerAddressId());

        if(!is_null($fflLicense)) {
            $order->setFflLicense($fflLicense);
        }
    }
}

