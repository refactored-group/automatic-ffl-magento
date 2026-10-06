<?php
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class ShippingRouting implements ArgumentInterface
{
    private $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    public function hasFflItem($quote, $items): bool
    {
        foreach ($items as $item) {
            $address = $this->helper->getMultishippingItemAddress($quote, $item);
            $state = $this->helper->multishippingRoutingState($quote, $address, $item);
            if ($this->helper->isFflItem($item, $quote, $state)) {
                return true;
            }
        }
        return false;
    }
}
