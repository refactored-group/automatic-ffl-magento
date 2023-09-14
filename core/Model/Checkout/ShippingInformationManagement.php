<?php

namespace RefactoredGroup\AutoFflCore\Model\Checkout;

use Magento\Quote\Model\QuoteRepository;

class ShippingInformationManagement
{
    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
    ) {

        if (!$extAttributes = $addressInformation->getExtensionAttributes()) {
            return null;
        }

        $quote = $this->quoteRepository->getActive($cartId);

        $quote->setFflLicense($extAttributes->getFflLicense());
        return null;
    }
}
