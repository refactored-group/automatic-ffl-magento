<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace Razoyo\AutoFflCheckoutMultiShipping\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Razoyo\AutoFflCheckoutMultiShipping\Helper\Data;

class Dealers extends Template
{
    const GOOGLE_MAPS_API_KEY_PATH = 'cms/pagebuilder/google_maps_api_key';
    const GOOGLE_MAPS_URL_PATH = 'autoffl/configuration/google_maps_api_url';
    const FFL_STORE_HASH_PATH = 'autoffl/configuration/store_hash';
    const FFL_STORE_URL_PATH = 'autoffl/configuration/ffl_api_url';

    /**
     * @var Data
     */
    private $helper;

    /**
     * @param Template\Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * Get JSON configuration for Dealers PopUp UI Component
     * @return string
     */
    public function getJsonConfig()
    {
        return json_encode([
            'store_hash' => $this->helper->getConfig(self::FFL_STORE_HASH_PATH),
            'google_maps_url' => $this->helper->getConfig(self::GOOGLE_MAPS_URL_PATH),
            'google_maps_api_key' => $this->helper->getConfig(self::GOOGLE_MAPS_API_KEY_PATH),
            'create_address_url' => $this->getUrl('createaddress/index/index'),
            'ffl_api_url' => $this->helper->getConfig(self::FFL_STORE_URL_PATH),
            'form_key' => $this->helper->getFormKey()
        ]);
    }
}
