<?php
namespace RefactoredGroup\AutoFflCore\Model\AdminNotification;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;
use RefactoredGroup\AutoFflCore\Model\AsyncOrderConfig;

class Configuration implements MessageInterface
{
    private $config;
    private $stores;
    private $asyncOrder;

    public function __construct(
        ScopeConfigInterface $config,
        StoreManagerInterface $stores,
        AsyncOrderConfig $asyncOrder
    ) {
        $this->config = $config;
        $this->stores = $stores;
        $this->asyncOrder = $asyncOrder;
    }

    public function getIdentity()
    {
        return 'autoffl_magento_upgrade_configuration';
    }

    public function isDisplayed()
    {
        return $this->hasEnabledStore() &&
            ($this->asyncOrder->isEnabled() || $this->hasIncompleteConnection());
    }

    public function getText()
    {
        if ($this->asyncOrder->isEnabled()) {
            return __('Automatic FFL does not support Magento AsyncOrder for this upgrade. Review checkout compatibility before using this mode.');
        }
        return __('An enabled Automatic FFL store view is missing its Store Hash or Store Secret. Configure both to browse categories and report placed orders.');
    }

    public function getSeverity()
    {
        return $this->asyncOrder->isEnabled()
            ? MessageInterface::SEVERITY_CRITICAL
            : MessageInterface::SEVERITY_MAJOR;
    }

    private function hasEnabledStore()
    {
        foreach ($this->stores->getStores() as $store) {
            if ($this->config->isSetFlag(Data::XML_PATH_IS_ENABLED, ScopeInterface::SCOPE_STORE, $store->getId())) {
                return true;
            }
        }
        return false;
    }

    private function hasIncompleteConnection()
    {
        foreach ($this->stores->getStores() as $store) {
            $storeId = $store->getId();
            if (!$this->config->isSetFlag(Data::XML_PATH_IS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
                continue;
            }
            if (!$this->config->getValue(Data::XML_PATH_STORE_HASH, ScopeInterface::SCOPE_STORE, $storeId) ||
                !$this->config->getValue(Data::XML_PATH_STORE_SECRET, ScopeInterface::SCOPE_STORE, $storeId)) {
                return true;
            }
        }
        return false;
    }
}
