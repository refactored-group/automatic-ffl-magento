<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace Razoyo\AutoFflCheckoutMultiShipping\Block\Checkout;

use Magento\Framework\View\Element\AbstractBlock;

class Addresses extends \Magento\Multishipping\Block\Checkout\Addresses
{
    /**
     * Generate the address field name for the current cart item
     * @param $item
     * @param $index
     * @return string
     */
    private function getFflAddressFieldName($item, $index)
    {
        return 'ship[' . $index . '][' . $item->getQuoteItemId() . '][address]';
    }

    /**
     * Get configurations for the Select Dealer UI Component
     * @param $item
     * @param $index
     * @return false|string
     */
    public function getSelectDealerConfig($item, $index) {
        return json_encode([
            'dealerButtonId' => $index,
            'addressFieldName' => $this->getFflAddressFieldName($item, $index)
        ]);
    }
}
