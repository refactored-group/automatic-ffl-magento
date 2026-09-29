<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCore\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use RefactoredGroup\AutoFflCore\Model\QuoteAnalysis;
use Magento\Multishipping\Helper\Data as MultishippingHelper;

/**
 * Data helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Configuration paths
     */
    const XML_PATH_STORE_HASH = 'autoffl/configuration/store_hash';
    const XML_PATH_IS_ENABLED = 'autoffl/configuration/enabled';
    const XML_PATH_GOOGLE_MAPS_API_KEY = 'autoffl/google_maps/api_key';
    const XML_PATH_GOOGLE_MAPS_API_URL = 'autoffl/configuration/google_maps_api_url';
    const XML_PATH_SANDBOX_MODE = 'autoffl/configuration/sandbox_mode';
    const XML_PATH_SHIP_NON_GUN_ITEMS = 'autoffl/configuration/ship_non_gun_items';
    const XML_PATH_STORE_SECRET = 'autoffl/configuration/store_secret';

    const API_PRODUCTION_URL = 'https://app.automaticffl.com/store-front/api';
    const API_SANDBOX_URL = 'https://app-stage.automaticffl.com/store-front/api';
    const MAP_PRODUCTION_URL = 'https://static.automaticffl.com/big-commerce-enhanced-checkout/index.html';
    const MAP_SANDBOX_URL = 'https://static-stage.automaticffl.com/big-commerce-enhanced-checkout/index.html';

    const DEFAULT_FIRSTNAME = 'FFL';
    const DEFAULT_LASTNAME = 'Dealer';
    const DEFAULT_FULLNAME = self::DEFAULT_FIRSTNAME . ' ' . self::DEFAULT_LASTNAME;

    /**
     * Checkout session
     *
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var bool|null
     */
    private $hasFfl = null;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    private $quote;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var bool
     */
    private $cartIsFfl = null;

    /**
     * @var bool|null
     */
    private $multishippingHelper;

    /**
     * @var bool|null
     */
    private $isEnabled = [];

    /** @var EncryptorInterface */
    private $encryptor;
    private $quoteAnalysis;
    private $regions;
    private $regionCodes = [];
    /**
     * @var bool|null
     */
    private $isMultiShipping = null;

    /**
     * Construct
     *
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        FormKey $formKey,
        MultishippingHelper $multishippingHelper,
        EncryptorInterface $encryptor,
        QuoteAnalysis $quoteAnalysis,
        RegionCollectionFactory $regions
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quote = $this->getQuote();
        $this->formKey = $formKey;
        $this->multishippingHelper = $multishippingHelper;
        $this->encryptor = $encryptor;
        $this->quoteAnalysis = $quoteAnalysis;
        $this->regions = $regions;

        parent::__construct($context);
    }

    /**
     * Retrieve checkout quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    private function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get FFL Store Hash
     *
     * @return string
     */
    public function getStoreHash($storeId = null)
    {
        return $this->getConfig(self::XML_PATH_STORE_HASH, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Google Maps API Key
     *
     * @return string
     */
    public function getGoogleMapsApiKey($storeId = null)
    {
        return $this->getConfig(self::XML_PATH_GOOGLE_MAPS_API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Google Maps API URL
     *
     * @return string
     */
    public function getGoogleMapsApiUrl($storeId = null)
    {
        return $this->getConfig(self::XML_PATH_GOOGLE_MAPS_API_URL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get FFL API URL
     *
     * @return string
     */
    public function getFflApiUrl($storeId = null)
    {
        if ($this->isSandboxMode($storeId)) {
            return self::API_SANDBOX_URL;
        }

        return self::API_PRODUCTION_URL;
    }

    /**
     * @return string
     */
    public function getDealersEndpoint($storeId = null)
    {
        return sprintf('%s/%s/%s', $this->getFflApiUrl($storeId), $this->getStoreHash($storeId), 'dealers');
    }

    /**
     * @return string
     */
    public function getStoresEndpoint($storeId = null)
    {
        return sprintf('%s/%s/%s', $this->getFflApiUrl($storeId), 'stores', $this->getStoreHash($storeId));
    }

    public function isSandboxMode($storeId = null)
    {
        return (bool) $this->getConfig(self::XML_PATH_SANDBOX_MODE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getStoreSecret($storeId = null)
    {
        $encrypted = $this->getConfig(self::XML_PATH_STORE_SECRET, ScopeInterface::SCOPE_STORE, $storeId);
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function getMapUrl($storeId = null)
    {
        $base = $this->isSandboxMode($storeId) ? self::MAP_SANDBOX_URL : self::MAP_PRODUCTION_URL;
        $params = ['store_hash' => $this->getStoreHash($storeId), 'platform' => 'Magento'];
        $key = trim((string) $this->getGoogleMapsApiKey($storeId));
        if ($key !== '') {
            $params['maps_api_key'] = $key;
        }
        return $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function getMapOrigin($storeId = null)
    {
        return $this->isSandboxMode($storeId)
            ? 'https://static-stage.automaticffl.com'
            : 'https://static.automaticffl.com';
    }

    /**
     * Verify if FFL is enabled
     * @return mixed
     */
    public function isEnabled($storeId = null)
    {
        $resolvedStoreId = $this->resolveStoreId($storeId);
        if (!array_key_exists($resolvedStoreId, $this->isEnabled)) {
            $this->isEnabled[$resolvedStoreId] = $this->getConfig(
                self::XML_PATH_IS_ENABLED, ScopeInterface::SCOPE_STORE, $resolvedStoreId
            );
        }
        return $this->isEnabled[$resolvedStoreId];
    }

    /**
     * Verify if non-gun items should be shipped together with FFL
     * @return mixed
     */
    public function shipNonGunItems($storeId = null)
    {
        return $this->getConfig(self::XML_PATH_SHIP_NON_GUN_ITEMS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Returns a bool of whether there is a FFL item in the cart
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function hasFflItem($quoteParam = false)
    {
        $quote = $quoteParam ?: $this->quote;
        $analysis = $this->quoteAnalysis->analyze($quote);
        return !empty($analysis['required']) || $analysis['unresolved'];
    }

    /**
     * Check if multishipping checkout is available
     * There should be a valid quote in checkout session. If not, only the config value will be returned
     *
     * @return bool
     */
    public function isMultishippingCheckoutAvailable()
    {
        if ($this->isMultiShipping === null) {
            $this->isMultiShipping = $this->multishippingHelper->isMultishippingCheckoutAvailable();
        }

        return $this->isMultiShipping && ((!$this->shipNonGunItems()) ||
                ($this->shipNonGunItems() && !$this->hasFflItem())
            );
    }

    /**
     * Verify if the current shopping cart is a FFL Cart.
     * A FFl cart is a shopping cart with FFL products only.
     *
     * @return bool|null
     */
    public function isFflCart()
    {
        if (!$this->isEnabled()) {
            return false;
        }
        return $this->quoteAnalysis->analyze($this->quote)['allRequired'];
    }

    /**
     * Decides whether the FFL components should be loaded on the checkout
     *
     * @return bool
     */
    public function isFfl()
    {
        return $this->isEnabled() && $this->hasFflItem();
    }

    public function hasConditionalAmmo()
    {
        return $this->isEnabled() && $this->quoteAnalysis->analyze($this->quote)['hasAmmunition'];
    }

    public function getRoutingState()
    {
        return strtoupper((string) $this->quote->getFflRoutingState());
    }

    public function getRoutingStateUrl()
    {
        return $this->_getUrl('autoffl/routing/state');
    }

    /**
     * @return bool
     */
    public function isMixedCart()
    {
        $analysis = $this->quoteAnalysis->analyze($this->quote);
        return !$analysis['unresolved'] && !empty($analysis['required']) && !$analysis['allRequired'];
    }

    public function isFflItem($item, $quote = null, $destinationState = null)
    {
        $quote = $quote ?: $this->quote;
        $quoteItemId = $item->getQuoteItemId() ?: $item->getId();
        foreach ($this->quoteAnalysis->analyze($quote, $destinationState)['required'] as $required) {
            if ((int) $required->getId() === (int) $quoteItemId) {
                return true;
            }
        }
        return false;
    }

    public function isUnresolvedAmmoItem($item, $quote, $destinationState)
    {
        $analysis = $this->quoteAnalysis->analyze($quote, $destinationState);
        if (!$analysis['unresolved']) {
            return false;
        }
        $quoteItemId = (int) ($item->getQuoteItemId() ?: $item->getId());
        foreach ($analysis['ammo'] as $entry) {
            if (empty($entry['states'])) {
                continue;
            }
            foreach ($entry['items'] as $ammoItem) {
                if ((int) $ammoItem->getId() === $quoteItemId) {
                    return true;
                }
            }
        }
        return false;
    }

    public function multishippingRoutingState($quote, $address)
    {
        if (!$address) {
            return '';
        }
        $customerAddressId = (string) $address->getCustomerAddressId();
        $data = json_decode((string) $quote->getFflDealerData(), true);
        if ($customerAddressId !== '' && is_array($data) &&
            !empty($data['addresses'][$customerAddressId]['routingState'])) {
            return strtoupper((string) $data['addresses'][$customerAddressId]['routingState']);
        }
        return $this->getAddressState($address);
    }

    public function getAddressState($address)
    {
        if (!$address) {
            return '';
        }
        $code = strtoupper(trim((string) $address->getRegionCode()));
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            return $code;
        }
        $regionId = (int) $address->getRegionId();
        if ($regionId <= 0) {
            return '';
        }
        if (!array_key_exists($regionId, $this->regionCodes)) {
            $region = $this->regions->create()
                ->addFieldToFilter('region_id', ['eq' => $regionId])
                ->getFirstItem();
            $this->regionCodes[$regionId] = strtoupper((string) $region->getCode());
        }
        return $this->regionCodes[$regionId];
    }

    /**
     * Get all FFL Items currently in the shopping cart
     * @return array
     */
    public function getFflItems()
    {
        return $this->quoteAnalysis->analyze($this->quote)['required'];
    }

    /**
     * Get all items of FFL products
     * @return string
     */
    public function getFflItemsNames()
    {
        $itemNames = [];
        foreach ($this->getFflItems() as $item) {
            $itemNames[] = $item->getName();
        }
        return implode(', ', $itemNames);
    }

    public function getCustomerQuote()
    {
        return $this->getQuote();
    }

    /**
     * Get a configuration value
     * @param $path
     * @return mixed
     */
    public function getConfig($path, $scope = ScopeInterface::SCOPE_STORE, $storeId = null)
    {
        if ($scope === ScopeInterface::SCOPE_STORE) {
            return $this->scopeConfig->getValue($path, $scope, $this->resolveStoreId($storeId));
        }
        return $this->scopeConfig->getValue($path, $scope, $storeId);
    }

    private function resolveStoreId($storeId)
    {
        if ($storeId !== null) {
            return (int) $storeId;
        }
        return (int) $this->quote->getStoreId();
    }

    /**
     * Get a valid form key
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get the default first name for all dealers
     * @return string
     */
    public function getDefaultFirstName()
    {
        $quote = $this->checkoutSession->getQuote();
        $customer = $quote->getCustomer();

        if ($customer && $customer->getId()) {
            
            return $customer->getFirstname();
        }
        return self::DEFAULT_FIRSTNAME;
    }

    /**
     * Get the default last name for all dealers
     * @return string
     */
    public function getDefaultLastName()
    {
        $quote = $this->checkoutSession->getQuote();
        $customer = $quote->getCustomer();

        if ($customer && $customer->getId()) {
            
            return $customer->getLastname();
        }
        return self::DEFAULT_LASTNAME;
    }
}
