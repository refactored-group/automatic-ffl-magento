<?php

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use RefactoredGroup\AutoFflCheckoutMultiShipping\Helper\Data as MsHelper;

class ClearCustomerSession implements ObserverInterface
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var MsHelper
     */
    private $msHelper;

    /**
     * @param Helper $helper
     * @param MsHelper $msHelper
     */
    public function __construct(
        Helper $helper,
        MsHelper $msHelper
    ) {
        $this->helper = $helper;
        $this->msHelper = $msHelper;
    }

    public function execute(Observer $observer)
    {
        if ($this->helper->isMultishippingCheckoutAvailable()) {
            $this->msHelper->clearCustomerSession();
        }
    }
}

