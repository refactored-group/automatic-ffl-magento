<?php

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use RefactoredGroup\AutoFflCheckoutMultiShipping\Helper\Data as MsHelper;
use RefactoredGroup\AutoFflCore\Model\AddressHandoff;

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
    private $handoff;

    /**
     * @param Helper $helper
     * @param MsHelper $msHelper
     */
    public function __construct(
        Helper $helper,
        MsHelper $msHelper,
        AddressHandoff $handoff
    ) {
        $this->helper = $helper;
        $this->msHelper = $msHelper;
        $this->handoff = $handoff;
    }

    public function execute(Observer $observer)
    {
        $this->handoff->clear();
        if ($this->helper->isMultishippingCheckoutAvailable()) {
            $this->msHelper->clearCustomerSession();
        }
    }
}
