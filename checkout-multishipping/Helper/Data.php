<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace Razoyo\AutoFflCheckoutMultiShipping\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;

/**
 * Data helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /*
     * Xml paths for multishipping checkout
     *
     **/
    const XML_PATH_CHECKOUT_MULTIPLE_AVAILABLE = 'multishipping/options/checkout_multiple';
    const XML_PATH_CHECKOUT_MULTIPLE_MAXIMUM_QUANTITY = 'multishipping/options/checkout_multiple_maximum_qty';

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
     * Construct
     *
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        FormKey $formKey
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quote = $this->getQuote();
        $this->formKey = $formKey;

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
     * Get maximum quantity allowed for shipping to multiple addresses
     *
     * @return int
     */
    private function getMaximumQty()
    {
        return (int)$this->getConfig(
            self::XML_PATH_CHECKOUT_MULTIPLE_MAXIMUM_QUANTITY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
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
        $isMultiShipping = $this->scopeConfig->isSetFlag(
            self::XML_PATH_CHECKOUT_MULTIPLE_AVAILABLE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        #VERIFY CONTROLLER
        return ($isMultiShipping) && $this->hasFflItem() && !$this->quote->hasItemsWithDecimalQty() &&
            $this->quote->validateMinimumAmount(true) &&
            $this->quote->getItemsSummaryQty() - $this->quote->getItemVirtualQty() > 0 &&
            $this->quote->getItemsSummaryQty() <= $this->getMaximumQty();
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
