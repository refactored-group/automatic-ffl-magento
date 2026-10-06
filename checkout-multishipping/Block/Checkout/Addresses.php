<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout;

use Magento\Customer\Model\Address\Config as AddressConfig;
use RefactoredGroup\AutoFflCore\Helper\Data as AutoFflHelper;
use RefactoredGroup\AutoFflCore\Model\RecipientName;

class Addresses extends \Magento\Multishipping\Block\Checkout\Addresses
{
    private $autoFflHelper;
    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Filter\DataObject\GridFactory $filterGridFactory
     * @param \Magento\Multishipping\Model\Checkout\Type\Multishipping $multishipping
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param AddressConfig $addressConfig
     * @param \Magento\Customer\Model\Address\Mapper $addressMapper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Filter\DataObject\GridFactory $filterGridFactory,
        \Magento\Multishipping\Model\Checkout\Type\Multishipping $multishipping,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        AddressConfig $addressConfig,
        \Magento\Customer\Model\Address\Mapper $addressMapper,
        AutoFflHelper $autoFflHelper,
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
        $this->autoFflHelper = $autoFflHelper;
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
    public function getSelectDealerConfig($item, $index, $isRecipientLead = false)
    {
        $quote = $this->getCheckout()->getQuote();
        $address = $this->autoFflHelper->getMultishippingItemAddress($quote, $item);
        $snapshots = json_decode((string) $quote->getFflDealerData(), true);
        $selected = $address && isset($snapshots['addresses'][(string) $address->getCustomerAddressId()]);
        $override = isset($snapshots['recipient']) && is_array($snapshots['recipient']) ? $snapshots['recipient'] : null;
        $recipient = RecipientName::fromSources($address, $quote->getCustomer(), $override);
        return json_encode([
            'dealerButtonId' => $index,
            'isRequiredFfl' => $this->isFflItem($item),
            'home_address_id' => $this->getDestinationAddressId($item),
            'collectRecipientName' => false,
            'isRecipientLead' => $isRecipientLead,
            'recipient_edited' => $override !== null,
            'recipient_address_id' => $address ? $address->getCustomerAddressId() : null,
            'recipient_firstname' => $recipient['firstname'],
            'recipient_lastname' => $recipient['lastname'],
            'selected_address_id' => $selected ? $address->getCustomerAddressId() : null,
            'selected_dealer_label' => $selected ? implode(', ', array_filter([
                $address->getCompany(), implode(' ', (array) $address->getStreet()), $address->getCity(),
                trim($address->getRegion() . ' ' . $address->getPostcode())
            ])) : '',
            'selected_address_label' => $selected ? implode(', ', array_filter([
                trim($address->getFirstname() . ' ' . $address->getLastname()),
                $address->getCompany(), implode(' ', (array) $address->getStreet()), $address->getCity(),
                trim($address->getRegion() . ' ' . $address->getPostcode())
            ])) : '',
            'addressFieldName' => $this->getFflAddressFieldName($item, $index),
            'routingState' => $this->autoFflHelper->multishippingRoutingState($quote, $address, $item)
        ]);
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $items = $this->getCheckout()->getQuoteShippingAddressesItems();
        // Magento initially creates one address item per unit. Keep each cart configuration on one row.
        $grouped = [];
        foreach ($items as $item) {
            $key = $item->getQuoteItemId();
            if (!isset($grouped[$key])) {
                $grouped[$key] = clone $item;
            } else {
                $qty = $this->positiveQtyOrZero($grouped[$key]->getQty())
                    + $this->positiveQtyOrZero($item->getQty());
                if ($this->isFflItem($item) && !$this->isFflItem($grouped[$key])) {
                    $grouped[$key] = clone $item;
                }
                $grouped[$key]->setQty($qty);
            }
        }
        $items = array_values($grouped);
        $items = $this->fillMissingItemQty($items);
        /** @var \Magento\Framework\Filter\DataObject\Grid $itemsFilter */
        $items = $this->sortItemsByFflFirst($items);
        $itemsFilter = $this->_filterGridFactory->create();
        $itemsFilter->addFilter(new \Magento\Framework\Filter\Sprintf('%d'), 'qty');
        return $itemsFilter->filter($items);
    }

    /**
     * Fall back once per grouped cart item when every address row lacks a quantity.
     *
     * @param array $items
     * @return array
     */
    private function fillMissingItemQty($items)
    {
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
     * @return float|int
     */
    private function positiveQtyOrZero($qty)
    {
        return $this->hasPositiveQty($qty) ? $qty : 0;
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
    public function getAddressesHtmlSelect($item, $index, $ammoDestination = false)
    {
        $addressId = $ammoDestination ? $this->getDestinationAddressId($item) : $item->getCustomerAddressId();
        $select = $this->getLayout()->createBlock(\Magento\Framework\View\Element\Html\Select::class)
            ->setName($ammoDestination ? 'ffl_destination[' . $index . '][' . $item->getQuoteItemId() . ']'
                : 'ship[' . $index . '][' . $item->getQuoteItemId() . '][address]')
            ->setId('ship_' . $index . '_' . $item->getQuoteItemId() . '_address')
            ->setClass('ship_address')
            ->setValue($addressId)
            ->setOptions($this->getAddressOptions())
            ->setExtraParams('data-mage-init=\'{ "RefactoredGroup_AutoFflCore/js/cart/shipping-address-select": '
                . ($ammoDestination ? json_encode($this->getAmmoDestinationConfig($item, $index)) : '{}') . ' }\'');

        return $select->getHtml();
    }

    private function getDestinationAddressId($item)
    {
        $id = $item->getCustomerAddressId();
        $data = json_decode((string) $this->getCheckout()->getQuote()->getFflDealerData(), true);
        return $data['addresses'][(string) $id]['routingAddressIds'][$item->getQuoteItemId()] ?? $id;
    }

    private function getAmmoDestinationConfig($item, $index): array
    {
        $quote = $this->getCheckout()->getQuote();
        $policies = [];
        $states = [];
        foreach ($this->customerRepository->getById($this->getCustomerId())->getAddresses() as $address) {
            $state = $this->autoFflHelper->getAddressState($address);
            if (!isset($states[$state])) {
                $states[$state] = $this->autoFflHelper->isFflItem($item, $quote, $state);
            }
            $region = $address->getRegion();
            $policies[$address->getId()] = [
                'state' => $state,
                'stateName' => $region && $region->getRegion() ? $region->getRegion() : $state,
                'required' => $states[$state]
            ];
        }
        return ['routeAmmo' => true, 'dealerButtonId' => $index, 'addressPolicies' => $policies];
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
            $positions = [];
            foreach ($this->getCheckout()->getQuote()->getAllVisibleItems() as $position => $quoteItem) {
                $positions[$quoteItem->getId()] = $position;
            }
            usort($items, function($a, $b) use ($positions) {
                if ($a->getQuoteItem() !== null && $b->getQuoteItem() !== null) {
                    // Fixed order: firearms, conditional ammunition, then other products.
                    $aGroup = $this->isConditionalAmmoItem($a) ? 1 : ($this->isFflItem($a) ? 0 : 2);
                    $bGroup = $this->isConditionalAmmoItem($b) ? 1 : ($this->isFflItem($b) ? 0 : 2);
                    if ($aGroup === $bGroup) {
                        return ($positions[$a->getQuoteItemId()] ?? 0) <=> ($positions[$b->getQuoteItemId()] ?? 0);
                    }
                    return $aGroup <=> $bGroup;
                }
                return 0;
            });
        }

        return $items;
    }

    public function isFflItem($item)
    {
        $quote = $this->getCheckout()->getQuote();
        $state = $this->autoFflHelper->multishippingRoutingState(
            $quote, $this->autoFflHelper->getMultishippingItemAddress($quote, $item), $item
        );
        return $this->autoFflHelper->isFflItem($item, $quote, $state);
    }

    public function requiresRoutingState($item)
    {
        $quote = $this->getCheckout()->getQuote();
        $state = $this->autoFflHelper->multishippingRoutingState(
            $quote, $this->autoFflHelper->getMultishippingItemAddress($quote, $item), $item
        );
        return $this->autoFflHelper->isUnresolvedAmmoItem($item, $quote, $state);
    }

    public function isConditionalAmmoItem($item)
    {
        return $this->autoFflHelper->isConditionalAmmoItem($item, $this->getCheckout()->getQuote());
    }
}
