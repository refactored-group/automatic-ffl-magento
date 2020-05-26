<?php

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config extends AbstractHelper {
    /**
     * @param Context $context
     */
    public function __construct( Context $context) {
        parent::__construct($context);
    }
    
    /**
     * @return bool
     */
    public function isEnabled($scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
        return $this->scopeConfig->isSetFlag( 'automaticffl/configuration/enabled', $scope );
    }

    /**
     * @return string
     */
    public function getApiKey($scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
        return $this->scopeConfig->getValue( 'automaticffl/configuration/api_key', $scope );
    }
}
