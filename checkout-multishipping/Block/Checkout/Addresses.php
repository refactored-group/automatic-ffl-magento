<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout;

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
    public function getSelectDealerConfig($item, $index)
    {
        return json_encode([
            'dealerButtonId' => $index,
            'addressFieldName' => $this->getFflAddressFieldName($item, $index)
        ]);
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $items = $this->getCheckout()->getQuoteShippingAddressesItems();
        /** @var \Magento\Framework\Filter\DataObject\Grid $itemsFilter */
        $items = $this->sortItemsByFflFirst($items);
        $itemsFilter = $this->_filterGridFactory->create();
        $itemsFilter->addFilter(new \Magento\Framework\Filter\Sprintf('%d'), 'qty');
        return $itemsFilter->filter($items);
    }

    /**
     * Retrieve HTML for addresses dropdown
     * 
     * Call the setExtraParams() method to attach a
     * data-mage-init attribute to the select field.
     *
     * @param mixed $item
     * @param int $index
     * @return string
     */
    public function getAddressesHtmlSelect($item, $index)
    {
        $select = $this->getLayout()->createBlock(\Magento\Framework\View\Element\Html\Select::class)
            ->setName('ship[' . $index . '][' . $item->getQuoteItemId() . '][address]')
            ->setId('ship_' . $index . '_' . $item->getQuoteItemId() . '_address')
            ->setClass('ship_address')
            ->setValue($item->getCustomerAddressId())
            ->setOptions($this->getAddressOptions())
            ->setExtraParams('data-mage-init=\'{ "RefactoredGroup_AutoFflCore/js/cart/handle-addresses-html-select": {} }\'');

        return $select->getHtml();
    }

    /**
     * This function sorts the value of the $items
     * If an FFL item is present in the shopping cart,
     * this brings all FFL items at the top of the array
     * and the non-FFL items at the bottom of the array.
     * 
     * @param array $items
     * 
     * @return array
     */
    private function sortItemsByFflFirst($items)
    {
        if (is_array($items) && count($items)) {
            usort($items, function($a, $b) {
                if ($a->getQuoteItem() !== null && $b->getQuoteItem() !== null) {
                    if ($a->getQuoteItem()->getProduct()->getRequiredFfl() ==
                        $b->getQuoteItem()->getProduct()->getRequiredFfl()) {
                        return 0;
                    }
                    return $a->getQuoteItem()->getProduct()->getRequiredFfl()
                        > $b->getQuoteItem()->getProduct()->getRequiredFfl()
                        ? -1 : 1;
                }
            });
        }

        return $items;
    }
}
