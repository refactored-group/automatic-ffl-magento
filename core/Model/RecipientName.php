<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\Exception\LocalizedException;

/** Shopper names belong to the shipping address, not the dealer-map snapshot. */
class RecipientName
{
    public static function fromSources($savedAddress, $customer, ?array $override = null): array
    {
        if ($override !== null) {
            return self::fromInput($override['firstname'] ?? null, $override['lastname'] ?? null);
        }
        foreach ([$savedAddress, $customer] as $source) {
            if (!$source) {
                continue;
            }
            try {
                return self::fromInput($source->getFirstname(), $source->getLastname());
            } catch (LocalizedException $error) {
                // An incomplete saved recipient falls back to the signed-in account.
            }
        }
        return ['firstname' => '', 'lastname' => ''];
    }

    public static function fromInput($firstname, $lastname): array
    {
        $names = [];
        foreach (['firstname' => $firstname, 'lastname' => $lastname] as $field => $value) {
            $value = is_string($value) ? preg_replace('/^\s+|\s+$/u', '', $value) : '';
            if (!$value || mb_strlen($value) > 255 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new LocalizedException(__($field === 'firstname'
                    ? 'Enter the recipient first name for dealer delivery.'
                    : 'Enter the recipient last name for dealer delivery.'));
            }
            $names[$field] = $value;
        }
        if (strcasecmp($names['firstname'], 'FFL') === 0 && strcasecmp($names['lastname'], 'Dealer') === 0) {
            throw new LocalizedException(__('Enter the recipient first and last name for dealer delivery.'));
        }
        return $names;
    }

    public static function applyToAddress($address): void
    {
        $names = self::fromInput($address ? $address->getFirstname() : null, $address ? $address->getLastname() : null);
        $address->setFirstname($names['firstname'])->setLastname($names['lastname']);
    }
}
