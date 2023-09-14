<?php

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Helper;

use \Magento\Customer\Model\Session;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var Session
     */
    protected $customerSession;

    public function __construct(
        Session $customerSession
    ) {
        $this->customerSession = $customerSession;
    }

    public function clearCustomerSession()
    {
        $keys = array_keys($this->customerSession->getData());
        foreach($keys as $key) {
            if(preg_match('/ffl_license_\d+/', $key) === 1) {
                $this->customerSession->unsetData($key);
            }
        }
    }
}
