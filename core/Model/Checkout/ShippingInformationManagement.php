<?php

namespace RefactoredGroup\AutoFflCore\Model\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement as MagentoShippingInformationManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\Exception\LocalizedException;
use RefactoredGroup\AutoFflCore\Helper\Data as AutoFflHelper;
use RefactoredGroup\AutoFflCore\Model\DealerSnapshot;
use RefactoredGroup\AutoFflCore\Model\QuoteAnalysis;

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
    private $snapshot;
    private $analysis;

    public function __construct(
        QuoteRepository $quoteRepository,
        AutoFflHelper $autoFflHelper,
        DealerSnapshot $snapshot,
        QuoteAnalysis $analysis
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->autoFflHelper = $autoFflHelper;
        $this->snapshot = $snapshot;
        $this->analysis = $analysis;
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
        if (!$this->autoFflHelper->isEnabled((int) $quote->getStoreId())) {
            return null;
        }
        $extAttributes = $addressInformation->getExtensionAttributes();
        $shippingAddress = $addressInformation->getShippingAddress();
        if (!$extAttributes || !$extAttributes->getFflLicense()) {
            $state = $this->autoFflHelper->getAddressState($shippingAddress);
            if (preg_match('/^[A-Z]{2}$/', $state) && $state !== $quote->getFflRoutingState()) {
                $quote->setFflRoutingState($state);
                $quote->setFflLicense(null);
                $quote->setFflDealerData(null);
            }
        }

        $analysis = $this->analysis->analyze($quote);
        if ($analysis['unresolved']) {
            throw new LocalizedException(__('Select a delivery state before continuing with ammunition.'));
        }

        if (!empty($analysis['required']) && !$analysis['allRequired']) {
            throw new LocalizedException(__('These items need separate shipping addresses. Continue with multishipping checkout.'));
        }

        if (empty($analysis['required'])) {
            $quote->setFflLicense(null);
            $quote->setFflDealerData(null);
            return null;
        }

        if (!$extAttributes || !$extAttributes->getFflLicense()) {
            throw new LocalizedException(__(self::FFL_REQUIRED_MESSAGE));
        }

        $quote->setFflLicense($extAttributes->getFflLicense());
        if (!$extAttributes->getFflDealerData()) {
            $quote->setFflDealerData(null);
            return null;
        }
        $snapshot = $this->snapshot->fromJson(
            $extAttributes->getFflDealerData(),
            $extAttributes->getFflLicense(),
            (int) $quote->getStoreId()
        );
        $quote->setFflDealerData(json_encode(['standard' => $snapshot]));
        return null;
    }
}
