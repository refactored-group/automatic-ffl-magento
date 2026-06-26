<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout;

use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Customer\Model\Session as CustomerSession;

class Addresses extends \Magento\Multishipping\Block\Checkout\Addresses
{
    private const FFL_CHECKOUT_BUTTON_KEY = 'ffl_checkout_button_clicked';
    private const FFL_CHECKOUT_BUTTON_VALUE = 'proceed_to_checkout';

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Filter\DataObject\GridFactory $filterGridFactory
     * @param \Magento\Multishipping\Model\Checkout\Type\Multishipping $multishipping
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param AddressConfig $addressConfig
     * @param \Magento\Customer\Model\Address\Mapper $addressMapper
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Filter\DataObject\GridFactory $filterGridFactory,
        \Magento\Multishipping\Model\Checkout\Type\Multishipping $multishipping,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        AddressConfig $addressConfig,
        \Magento\Customer\Model\Address\Mapper $addressMapper,
        CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $filterGridFactory,
            $multishipping,
            $customerRepository,
            $addressConfig,
            $addressMapper,
            $data
        );

        $this->customerSession = $customerSession;
    }

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
            'addressFieldName' => $this->getFflAddressFieldName($item, $index),
            'groupedFflCheckout' => $this->isGroupedFflCheckout()
        ]);
    }

    /**
     * Check whether normal checkout redirected a mixed FFL cart into multishipping.
     *
     * @return bool
     */
    public function isGroupedFflCheckout(): bool
    {
        return $this->customerSession->getData(self::FFL_CHECKOUT_BUTTON_KEY) === self::FFL_CHECKOUT_BUTTON_VALUE;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $items = $this->getCheckout()->getQuoteShippingAddressesItems();
        /** @var \Magento\Framework\Filter\DataObject\Grid $itemsFilter */
        $items = $this->sortItemsByFflFirst($items);
        $items = $this->fillMissingItemQty($items);
        $itemsFilter = $this->_filterGridFactory->create();
        $itemsFilter->addFilter(new \Magento\Framework\Filter\Sprintf('%d'), 'qty');
        return $itemsFilter->filter($items);
    }

    /**
     * Quote address items can arrive without a qty before an FFL dealer address
     * has been selected. Fall back to the owning quote item so the qty input is
     * not rendered empty.
     *
     * @param array $items
     * @return array
     */
    private function fillMissingItemQty($items)
    {
        if (!is_array($items)) {
            return $items;
        }

        foreach ($items as $item) {
            if ($this->hasPositiveQty($item->getQty())) {
                continue;
            }

            $quoteItem = $item->getQuoteItem();
            if ($quoteItem && $this->hasPositiveQty($quoteItem->getQty())) {
                $item->setQty($quoteItem->getQty());
            }
        }

        return $items;
    }

    /**
     * @param mixed $qty
     * @return bool
     */
    private function hasPositiveQty($qty): bool
    {
        return is_numeric($qty) && (float)$qty > 0;
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
            ->setExtraParams(
                'data-mage-init=\'{ "RefactoredGroup_AutoFflCore/js/cart/shipping-address-select": '
                . $this->getGroupedFflCheckoutConfig() . ' }\''
            );

        return $select->getHtml();
    }

    /**
     * @return string
     */
    private function getGroupedFflCheckoutConfig(): string
    {
        return json_encode([
            'groupedFflCheckout' => $this->isGroupedFflCheckout()
        ]);
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
