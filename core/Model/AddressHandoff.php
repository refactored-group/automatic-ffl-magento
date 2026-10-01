<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

/** Keeps the customer's home address across native multishipping login and cart initialization. */
class AddressHandoff
{
    private const SESSION_KEY = 'autoffl_shipping_handoff';
    private $session;
    private $addresses;
    private $addressFactory;

    public function __construct(Session $session, AddressRepositoryInterface $addresses, AddressInterfaceFactory $addressFactory)
    {
        $this->session = $session;
        $this->addresses = $addresses;
        $this->addressFactory = $addressFactory;
    }

    public function capture($quote, array $input, $regionId)
    {
        $address = [];
        foreach (['firstname', 'lastname', 'company', 'city', 'postcode', 'telephone'] as $field) {
            $address[$field] = isset($input[$field]) && is_scalar($input[$field])
                ? mb_substr(trim((string) $input[$field]), 0, 255) : '';
        }
        $address['street'] = [];
        foreach (array_slice((array) ($input['street'] ?? []), 0, 4) as $line) {
            if (is_scalar($line) && trim((string) $line) !== '') {
                $address['street'][] = mb_substr(trim((string) $line), 0, 255);
            }
        }
        $address['country_id'] = 'US';
        $address['region_id'] = (int) $regionId;
        $address['customer_address_id'] = (int) ($input['customerAddressId'] ?? 0);
        foreach (['firstname', 'lastname', 'city', 'postcode', 'telephone'] as $field) {
            if ($address[$field] === '') {
                return;
            }
        }
        if (!$address['street'] || !$address['region_id']) {
            return;
        }
        $this->session->setData(self::SESSION_KEY, [
            'storeId' => (int) $quote->getStoreId(), 'createdAt' => time(), 'address' => $address
        ]);
    }

    public function restoreForCustomer($quote, $customer)
    {
        $pending = $this->getPending($quote);
        if (!$pending || !$customer->getId()) {
            return null;
        }
        $data = $pending['address'];
        try {
            if ($data['customer_address_id']) {
                $saved = $this->addresses->getById($data['customer_address_id']);
                if ((int) $saved->getCustomerId() === (int) $customer->getId() && $this->sameAddress($saved, $data)) {
                    $this->session->unsetData(self::SESSION_KEY);
                    return $saved->getId();
                }
            }
            $address = $this->addressFactory->create();
            $address->setCustomerId($customer->getId());
            $address->setFirstname($data['firstname']);
            $address->setLastname($data['lastname']);
            $address->setCompany($data['company']);
            $address->setStreet($data['street']);
            $address->setCity($data['city']);
            $address->setPostcode($data['postcode']);
            $address->setTelephone($data['telephone']);
            $address->setCountryId('US');
            $address->setRegionId($data['region_id']);
            $saved = $this->addresses->save($address);
            $this->session->unsetData(self::SESSION_KEY);
            return $saved->getId();
        } catch (LocalizedException $exception) {
            // Custom address requirements can still be completed in Magento's native address form.
            return null;
        }
    }

    public function getPending($quote)
    {
        $pending = $this->session->getData(self::SESSION_KEY);
        if (!is_array($pending)) {
            return null;
        }
        if ((int) $pending['storeId'] !== (int) $quote->getStoreId() || time() - $pending['createdAt'] > 7200) {
            $this->session->unsetData(self::SESSION_KEY);
            return null;
        }
        return $pending;
    }

    private function sameAddress($saved, array $data)
    {
        foreach (['firstname', 'lastname', 'company', 'city', 'postcode', 'telephone'] as $field) {
            $getter = 'get' . ucfirst($field);
            if (trim((string) $saved->$getter()) !== $data[$field]) {
                return false;
            }
        }
        return $saved->getCountryId() === 'US' && (int) $saved->getRegionId() === $data['region_id'] &&
            array_values((array) $saved->getStreet()) === $data['street'];
    }

    public function clear()
    {
        $this->session->unsetData(self::SESSION_KEY);
    }
}
