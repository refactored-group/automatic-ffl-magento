<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout;

class Shipping extends \Magento\Multishipping\Block\Checkout\Shipping
{
    /**
     * Generate the address field name for the current cart item
     * @param $item
     * @param $index
     * @return string
     */
    private function getFflAddressFieldName($item, $index)
    {
        return 'ship[' . $index . '][' . $item . '][address]';
    }

    /**
     * Get configurations for the Select Dealer UI Component
     * @param $item
     * @param $index
     * @return false|string
     */
    public function getSelectDealerConfig($item, $index)
    {
        return json_encode([
            'dealerButtonId' => $index,
            'addressFieldName' => $this->getFflAddressFieldName($item, $index)
        ]);
    }

    /**
     * Verify if current shipping entry has any FFL products
     * @param $items
     * @return bool
     */
    public function hasFflItem($items) {
        foreach ($items as $item) {
            if ($item->getProduct()->getRequiredFfl()) {
                return true;
            }
        }
        return false;
    }
}
