<?php
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin;

use Magento\Framework\App\RequestInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;
use RefactoredGroup\AutoFflCore\Model\AddressHandoff;

/** Retain entered fields when a merchant requires additional address data. */
class AddressForm
{
    private $request;
    private $helper;
    private $handoff;

    public function __construct(RequestInterface $request, Data $helper, AddressHandoff $handoff)
    {
        $this->request = $request;
        $this->helper = $helper;
        $this->handoff = $handoff;
    }

    public function afterGetAddress($subject, $address)
    {
        if (!$address || strtolower($this->request->getFullActionName()) !== 'multishipping_checkout_address_newshipping' || $address->getId()) {
            return $address;
        }
        $pending = $this->handoff->getPending($this->helper->getCustomerQuote());
        if ($pending) {
            $data = $pending['address'];
            $address->setFirstname($data['firstname'])->setLastname($data['lastname'])
                ->setCompany($data['company'])->setStreet($data['street'])->setCity($data['city'])
                ->setPostcode($data['postcode'])->setTelephone($data['telephone'])
                ->setCountryId('US')->setRegionId($data['region_id']);
            $this->handoff->clear();
        }
        return $address;
    }
}
