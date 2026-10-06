<?php
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Model;

use Magento\Framework\Exception\LocalizedException;

class DealerGrouping
{
    public static function assign($quote, array $rows, array $destinations, $dealerId, $helper, $addresses): array
    {
        $data = json_decode((string) $quote->getFflDealerData(), true) ?: [];
        foreach ($rows as $index => &$row) {
            foreach ($row as $itemId => &$selection) {
                $item = $quote->getItemById($itemId);
                if (!$item || $item->getProduct()->getIsVirtual() || (int) ($selection['qty'] ?? 0) <= 0) {
                    continue;
                }
                $id = (int) $selection['address'];
                $previous = $data['addresses'][$id] ?? [];
                $ammo = $helper->isConditionalAmmoItem($item, $quote);
                $sourceId = (int) ($ammo ? ($destinations[$index][$itemId]
                    ?? $previous['routingAddressIds'][$itemId] ?? $id)
                    : ($previous['routingAddressIds'][$itemId] ?? $id));
                $source = $addresses->getById($sourceId);
                if ((int) $source->getCustomerId() !== (int) $quote->getCustomerId()) {
                    throw new LocalizedException(__('The selected shipping address is unavailable.'));
                }
                $state = $ammo && isset($destinations[$index][$itemId]) ? $helper->getAddressState($source)
                    : ($previous['routingStates'][$itemId] ?? $previous['routingState'] ?? $helper->getAddressState($source));
                if ($helper->isFflItem($item, $quote, $state)) {
                    $selection['address'] = $dealerId;
                    $data['addresses'][$dealerId]['routingStates'][$itemId] = $state;
                    $data['addresses'][$dealerId]['routingAddressIds'][$itemId] = $sourceId;
                }
            }
            unset($selection);
        }
        unset($row);
        if (strlen(json_encode($data)) > 60000) {
            throw new LocalizedException(__('Too many dealer selections are saved on this cart. Refresh the cart and try again.'));
        }
        $quote->setFflDealerData(json_encode($data));
        return $rows;
    }

    public static function normalize($quote, array $rows, array $destinations, $helper, $addresses, $requireDealer = false): array
    {
        $data = json_decode((string) $quote->getFflDealerData(), true) ?: [];
        $members = [];
        $dealerId = null;
        $dealerIsAmmo = true;
        $owned = [];
        $getAddress = static function ($id) use ($quote, $addresses, &$owned) {
            if (!$id) {
                throw new LocalizedException(__('Select a saved shipping address for every item.'));
            }
            if (!isset($owned[$id])) {
                $owned[$id] = $addresses->getById($id);
                if ((int) $owned[$id]->getCustomerId() !== (int) $quote->getCustomerId()) {
                    throw new LocalizedException(__('The selected shipping address is unavailable.'));
                }
            }
            return $owned[$id];
        };
        foreach ($rows as $index => $row) {
            foreach ($row as $itemId => $selection) {
                $item = $quote->getItemById($itemId);
                if (!$item || $item->getProduct()->getIsVirtual() || (int) ($selection['qty'] ?? 0) <= 0) {
                    continue;
                }
                $ammo = $helper->isConditionalAmmoItem($item, $quote);
                $current = $helper->getMultishippingItemAddress($quote, $item);
                $id = (int) ($selection['address'] ?? 0);
                $id = $id ?: ($current ? (int) $current->getCustomerAddressId() : 0);
                $address = $getAddress($id);
                $snapshot = $data['addresses'][$id] ?? null;
                $sourceId = $ammo ? (int) ($destinations[$index][$itemId]
                    ?? $snapshot['routingAddressIds'][$itemId] ?? $id) : $id;
                $source = $getAddress($sourceId);
                $state = $ammo && isset($destinations[$index][$itemId])
                    ? $helper->getAddressState($source)
                    : ($snapshot['routingStates'][$itemId] ?? $snapshot['routingState']
                        ?? $helper->getAddressState($address));
                $rows[$index][$itemId]['address'] = $ammo ? $sourceId : $id;
                if ($requireDealer && $helper->isUnresolvedAmmoItem($item, $quote, $state)) {
                    throw new LocalizedException(__('Select a delivery state for ammunition.'));
                }
                if (!$helper->isFflItem($item, $quote, $state)) {
                    continue;
                }
                $members[] = [$index, $itemId, $state, $sourceId];
                if (!empty($snapshot['license']) && ($dealerId === null || ($dealerIsAmmo && !$ammo))) {
                    $dealerId = $id;
                    $dealerIsAmmo = $ammo;
                }
            }
        }
        if ($requireDealer && !$members) {
            throw new LocalizedException(__('This cart does not require a dealer selection.'));
        }
        if ($dealerId !== null) {
            foreach ($members as [$index, $itemId, $state, $sourceId]) {
                $rows[$index][$itemId]['address'] = $dealerId;
                $data['addresses'][$dealerId]['routingStates'][$itemId] = $state;
                $data['addresses'][$dealerId]['routingAddressIds'][$itemId] = $sourceId;
            }
            $encoded = json_encode($data);
            if (strlen($encoded) > 60000) {
                throw new LocalizedException(__('Too many dealer selections are saved on this cart. Refresh the cart and try again.'));
            }
            $quote->setFflDealerData($encoded);
        }
        return $rows;
    }
}
