<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Plugin;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use RefactoredGroup\AutoFflCore\Model\QuoteAnalysis;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class ModelOrderPlugin
{
    /**
     * @var Helper
     */
    private $helper;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var ResponseFactory
     */
    private $responseFactory;
    /**
     * @var Request
     */
    private $request;
    private $analysis;
    private $quotes;

    /**
     * @param Helper $helper
     * @param ManagerInterface $messageManager
     * @param UrlInterface $url
     * @param ResponseFactory $responseFactory
     * @param Request $request
     */
    public function __construct(
        Helper $helper,
        ManagerInterface $messageManager,
        UrlInterface $url,
        ResponseFactory $responseFactory,
        Request $request,
        QuoteAnalysis $analysis,
        CartRepositoryInterface $quotes
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->url = $url;
        $this->responseFactory = $responseFactory;
        $this->request = $request;
        $this->analysis = $analysis;
        $this->quotes = $quotes;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @return void
     */
    public function beforePlace(\Magento\Sales\Model\Order $subject)
    {
        $storeId = (int) $subject->getStoreId();
        if (!$this->helper->isEnabled($storeId)) {
            return;
        }
        $quote = $subject->getQuote();
        if (!$quote instanceof Quote) {
            $quoteId = (int) $subject->getQuoteId();
            if ($quoteId <= 0) {
                return;
            }
            $quote = $this->quotes->get($quoteId);
        }
        if ((int) $quote->getStoreId() !== $storeId) {
            throw new LocalizedException(__('The FFL order store does not match the cart.'));
        }
        $snapshot = json_decode((string) $subject->getFflDealerData(), true);
        $shipping = $subject->getShippingAddress();
        $quoteSnapshot = json_decode((string) $quote->getFflDealerData(), true);
        if (is_array($quoteSnapshot) && isset($quoteSnapshot['addresses'])) {
            // A dealer address can differ from the shopper's original destination.
            $destinationState = is_array($snapshot)
                ? ($snapshot['routingState'] ?? '')
                : $this->helper->getAddressState($shipping);
        } else {
            $destinationState = is_array($snapshot) || $subject->getFflLicense()
                ? $quote->getFflRoutingState() : $this->helper->getAddressState($shipping);
        }
        $analysis = $this->analysis->analyze($quote, $destinationState);
        if (!$quote->getIsMultiShipping() && !empty($analysis['required']) && !$analysis['allRequired']) {
            throw new LocalizedException(__('These items need separate shipping addresses. Continue with multishipping checkout.'));
        }
        $requiredQuoteItemIds = [];
        foreach ($analysis['required'] as $item) {
            $requiredQuoteItemIds[(int) $item->getId()] = true;
        }
        $ammoQuoteItemIds = [];
        foreach ($analysis['ammo'] as $entry) {
            foreach ($entry['items'] as $item) {
                $ammoQuoteItemIds[(int) $item->getId()] = true;
            }
        }
        $needsDealer = false;
        foreach ($subject->getAllVisibleItems() as $item) {
            $quoteItemId = (int) $item->getQuoteItemId();
            if ($analysis['unresolved'] && isset($ammoQuoteItemIds[$quoteItemId])) {
                throw new LocalizedException(__('Select a delivery state before placing an ammunition order.'));
            }
            if (isset($requiredQuoteItemIds[$quoteItemId])) {
                $needsDealer = true;
            }
        }
        if (!$needsDealer) {
            return;
        }
        if (!is_array($snapshot) && $quote->getFflDealerData() === null && $subject->getFflLicense()) {
            // Orders from an already-open legacy checkout tab retain their license-only contract.
            return;
        }
        if (!is_array($snapshot) || empty($snapshot['id']) || empty($snapshot['license']) ||
            !isset($snapshot['storeHash'], $snapshot['sandbox'], $snapshot['state'], $snapshot['postalCode']) ||
            $snapshot['storeHash'] !== $this->helper->getStoreHash($storeId) ||
            (bool) $snapshot['sandbox'] !== $this->helper->isSandboxMode($storeId) ||
            $snapshot['license'] !== $subject->getFflLicense()) {
            throw new LocalizedException(__('Select a licensed dealer before placing this order.'));
        }
        if (!$shipping || $this->helper->getAddressState($shipping) !== $snapshot['state'] ||
            strtoupper((string) $shipping->getCountryId()) !== 'US' ||
            trim((string) $shipping->getPostcode()) !== $snapshot['postalCode'] ||
            trim((string) $shipping->getCity()) !== $snapshot['city']) {
            throw new LocalizedException(__('The dealer shipping address does not match the selection.'));
        }
    }
}
