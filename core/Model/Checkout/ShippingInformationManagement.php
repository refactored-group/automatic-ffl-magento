<?php

namespace RefactoredGroup\AutoFflCore\Model\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement as MagentoShippingInformationManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\Exception\LocalizedException;
use RefactoredGroup\AutoFflCore\Helper\Data as AutoFflHelper;

class ShippingInformationManagement
{
    private const FFL_REQUIRED_MESSAGE = 'Please select a Licensed Firearm Dealer (FFL) before continuing.';

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

    /**
     * Require a dealer selection for any cart containing an FFL item.
     *
     * The dealer shipping address itself is applied on the client when a dealer is
     * selected; stale checkout state is wiped on every checkout load (see
     * RefactoredGroup\AutoFflCore\Observer\Checkout\Index) so a fresh dealer choice
     * is always required. Here we only enforce that a license is present and persist
     * it to the quote.
     */
    public function beforeSaveAddressInformation(
        MagentoShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $quote = $this->quoteRepository->getActive($cartId);

        if (!$this->autoFflHelper->hasFflItem($quote)) {
            $quote->setFflLicense(null);
            return null;
        }

        $extAttributes = $addressInformation->getExtensionAttributes();
        if (!$extAttributes || !$extAttributes->getFflLicense()) {
            throw new LocalizedException(__(self::FFL_REQUIRED_MESSAGE));
        }

        $quote->setFflLicense($extAttributes->getFflLicense());
        return null;
    }
}
