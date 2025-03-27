<?php

namespace RefactoredGroup\AutoFflCore\Model\Checkout;

use Magento\Quote\Model\QuoteRepository;
use RefactoredGroup\AutoFflCore\Helper\Data as AutoFflHelper;
use Magento\Framework\Exception\LocalizedException;

class ShippingInformationManagement
{
    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var AutoFflHelper
     */
    protected $autoFflHelper;

    public function __construct(
        QuoteRepository $quoteRepository,
        AutoFflHelper $autoFflHelper
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->autoFflHelper = $autoFflHelper;
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

        if (!$extAttributes->getFflLicense() && $this->autoFflHelper->hasFflItem($quote)) {
            throw new LocalizedException(__('Please select a Licensed Firearm Dealer (FFL) before continuing.'));
        }

        $quote->setFflLicense($extAttributes->getFflLicense());
        return null;
    }
}
