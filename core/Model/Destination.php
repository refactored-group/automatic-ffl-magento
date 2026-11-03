<?php
namespace RefactoredGroup\AutoFflCore\Model;

/** Resolves Magento country/region addresses to Automatic FFL jurisdiction codes. */
class Destination
{
    private const TERRITORY_COUNTRIES = ['AS', 'GU', 'MP', 'VI'];

    public static function resolve($countryId, $regionCode = '')
    {
        $country = strtoupper(trim((string) $countryId));
        $region = strtoupper(trim((string) $regionCode));
        if (in_array($country, self::TERRITORY_COUNTRIES, true)) {
            return $country;
        }
        return $country === 'US' && preg_match('/^[A-Z]{2}$/', $region) ? $region : '';
    }

    public static function isTerritoryCountry($countryId)
    {
        return in_array(strtoupper(trim((string) $countryId)), self::TERRITORY_COUNTRIES, true);
    }
}
