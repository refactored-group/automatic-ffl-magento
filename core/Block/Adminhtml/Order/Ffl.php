<?php
namespace RefactoredGroup\AutoFflCore\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use RefactoredGroup\AutoFflCore\Model\OrderFflData;

/**
 * Read-only FFL details, separate from the order's shipping address.
 */
class Ffl extends Template
{
    private $registry;
    private $fflData;

    public function __construct(Context $context, Registry $registry, OrderFflData $fflData, array $data = [])
    {
        $this->registry = $registry;
        $this->fflData = $fflData;
        parent::__construct($context, $data);
    }

    public function getFflData()
    {
        $order = $this->getData('order') ?: $this->registry->registry('current_order')
            ?: $this->registry->registry('order');
        return $order instanceof DataObject ? $this->fflData->fromOrder($order) : [];
    }
}
