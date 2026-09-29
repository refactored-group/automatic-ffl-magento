<?php
namespace RefactoredGroup\AutoFflCore\Model\AdminNotification;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class Configuration implements MessageInterface
{
    private $config;
    private $stores;
    private $modules;

    public function __construct(
        ScopeConfigInterface $config,
        StoreManagerInterface $stores,
        ModuleManager $modules
    ) {
        $this->config = $config;
        $this->stores = $stores;
        $this->modules = $modules;
    }

    public function getIdentity()
    {
        return 'autoffl_magento_upgrade_configuration';
    }

    public function isDisplayed()
    {
        return $this->hasEnabledStore() &&
            ($this->modules->isEnabled('Magento_AsyncOrder') || $this->hasIncompleteConnection());
    }

    public function getText()
    {
        if ($this->modules->isEnabled('Magento_AsyncOrder')) {
            return __('Automatic FFL does not support Magento AsyncOrder for this upgrade. Review checkout compatibility before using this mode.');
        }
        return __('An enabled Automatic FFL store view is missing its Store Hash or Store Secret. Configure both to browse categories and report placed orders.');
    }

    public function getSeverity()
    {
        return $this->modules->isEnabled('Magento_AsyncOrder')
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
