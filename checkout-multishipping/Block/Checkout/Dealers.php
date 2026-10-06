<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout;

use Magento\Framework\View\Element\Template;
use RefactoredGroup\AutoFflCore\Helper\Data;

class Dealers extends Template
{
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
        array $data = []
    ) {
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
            'iframeUrl' => $this->helper->getMapUrl(),
            'iframeOrigin' => $this->helper->getMapOrigin(),
            'create_address_url' => $this->getUrl('createaddress/index/index'),
            'form_key' => $this->helper->getFormKey(),
            'is_ffl' => true,
            'mode' => 'cart'
        ]);
    }
}
