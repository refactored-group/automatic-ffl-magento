<?php
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Model;

use Magento\Framework\Exception\LocalizedException;
use RefactoredGroup\AutoFflCore\Model\RecipientName;

/** Explicit edits apply to this shared shipment, never to a saved home address. */
class Recipient
{
    public static function apply($quote, array $recipients, $addresses): void
    {
        $data = json_decode((string) $quote->getFflDealerData(), true) ?: [];
        $updates = [];
        $chosen = null;
        foreach ($recipients as $recipient) {
            if (!is_array($recipient)) {
                throw new LocalizedException(__('Enter a recipient name for dealer delivery.'));
            }
            if (isset($recipient['override']) && (string) $recipient['override'] !== '1') {
                continue;
            }
            $names = RecipientName::fromInput($recipient['firstname'] ?? null, $recipient['lastname'] ?? null);
            if ($chosen !== null && $chosen !== $names) {
                throw new LocalizedException(__('Use one recipient name for the shared dealer shipment.'));
            }
            $chosen = $names;
            $id = (int) ($recipient['address_id'] ?? 0);
            if (!$id) {
                continue;
            }
            if (!isset($data['addresses'][(string) $id])) {
                throw new LocalizedException(__('Select a dealer before changing its recipient name.'));
            }
            $address = $addresses->getById($id);
            $deleted = $address->getCustomAttribute('is_deleted');
            if (!(int) $quote->getCustomerId() || (int) $address->getCustomerId() !== (int) $quote->getCustomerId() ||
                !$deleted || (int) $deleted->getValue() !== 1) {
                throw new LocalizedException(__('The selected dealer address is unavailable. Please select it again.'));
            }
            $updates[$id] = $address;
        }
        if ($chosen === null) {
            return;
        }
        // Validate all inputs and ownership before any address is changed.
        foreach ($updates as $id => $address) {
            $address->setFirstname($chosen['firstname'])->setLastname($chosen['lastname']);
            $addresses->save($address);
            foreach ($quote->getAllShippingAddresses() as $shipping) {
                if ((int) $shipping->getCustomerAddressId() === $id) {
                    $shipping->setFirstname($chosen['firstname'])->setLastname($chosen['lastname']);
                }
            }
        }
        $data['recipient'] = $chosen;
        $quote->setFflDealerData(json_encode($data));
    }
}
