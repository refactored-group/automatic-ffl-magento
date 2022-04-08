<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace Razoyo\AutoFflCore\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
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
    const XML_PATH_GOOGLE_MAPS_API_KEY = 'cms/pagebuilder/google_maps_api_key';
    const XML_PATH_GOOGLE_MAPS_API_URL = 'autoffl/configuration/google_maps_api_url';
    const XML_PATH_FFL_API_URL = 'autoffl/configuration/ffl_api_url';

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
    private $isEnabled = null;
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
        MultishippingHelper $multishippingHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quote = $this->getQuote();
        $this->formKey = $formKey;
        $this->multishippingHelper = $multishippingHelper;

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
    public function getStoreHash()
    {
        return $this->getConfig(
            self::XML_PATH_STORE_HASH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Google Maps API Key
     *
     * @return string
     */
    public function getGoogleMapsApiKey()
    {
        return $this->getConfig(
            self::XML_PATH_GOOGLE_MAPS_API_KEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Google Maps API URL
     *
     * @return string
     */
    public function getGoogleMapsApiUrl()
    {
        return $this->getConfig(
            self::XML_PATH_GOOGLE_MAPS_API_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get FFL API URL
     *
     * @return string
     */
    public function getFflApiUrl()
    {
        return $this->getConfig(
            self::XML_PATH_FFL_API_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Verify if FFL is enabled
     * @return mixed
     */
    public function isEnabled()
    {
        if ($this->isEnabled === null) {
            $this->isEnabled = $this->getConfig(
                self::XML_PATH_IS_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }
        return $this->isEnabled;
    }

    /**
     * Returns a bool of whether there is a FFL item in the cart
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function hasFflItem()
    {
        if ($this->hasFfl !== null) {
            return $this->hasFfl;
        }
        $this->hasFfl = false;
        $items = $this->quote->getAllVisibleItems();
        foreach ($items as $item) {
            if ($item->getProduct()->getRequiredFfl()) {
                $this->hasFfl = true;
            }
        }
        return $this->hasFfl;
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

        return $this->isMultiShipping && $this->hasFflItem();
    }

    /**
     * Verify if the current shopping cart is a FFL Cart.
     * A FFl cart is a shopping cart with FFL products only.
     * @return bool|null
     */
    public function isFflCart()
    {
        if ($this->cartIsFfl === null && $this->isEnabled()) {
            $totalFflItems = 0;
            $visibleCartItems = $this->quote->getAllVisibleItems();
            $totalVisibleItems = count($visibleCartItems);

            foreach ($visibleCartItems as $item) {
                if ($item->getProduct()->getRequiredFfl() == 1) {
                    $totalFflItems++;
                }
            }

            $this->cartIsFfl = $totalVisibleItems == $totalFflItems;
        }

        return $this->cartIsFfl;
    }

    /**
     * Get all FFL Items currently in the shopping cart
     * @return array
     */
    public function getFflItems()
    {
        $fflItems = [];
        foreach ($this->quote->getAllVisibleItems() as $item) {
            if ($item->getProduct()->getRequiredFfl()) {
                $fflItems[] = $item;
            }
        }
        return $fflItems;
    }

    /**
     * Get all items of FFL products
     * @return string
     */
    public function getFflItemsNames()
    {
        $itemNames = [];
        foreach ($this->getFflItems() as $item) {
            $itemNames = $item->getName();
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
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path);
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
}
