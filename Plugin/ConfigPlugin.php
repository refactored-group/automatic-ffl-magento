<?php

namespace Razoyo\AutomaticFfl\Plugin;

class ConfigPlugin
{
    /**
     * @var string
     */
    const FFL_STORE_HASH_PATH = 'automaticffl/configuration/store_hash';

    /**
     * @var string
     */
    const FFL_IS_ENABLED_PATH = 'automaticffl/configuration/enabled';

    /**
     * @var string
     */
    protected $fflSearchUrl = 'https://app.automaticffl.com/big_commerce/api/dealers/active';

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     */
    public function __construct(
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->messageManager = $messageManager;
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
    }

    /**
     * @param \Magento\Config\Model\Config $subject
     * @param callable $proceed
     */
    public function aroundSave(\Magento\Config\Model\Config $subject, callable $proceed) 
    {
        $oldConfig = [
            self::FFL_STORE_HASH_PATH => $this->scopeConfig->getValue(self::FFL_STORE_HASH_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE), 
            self::FFL_IS_ENABLED_PATH => $this->scopeConfig->getValue(self::FFL_IS_ENABLED_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ];
        $proceed();
        $config = $subject->load();
        if (isset($config[self::FFL_STORE_HASH_PATH])) {
            if ($oldConfig[self::FFL_STORE_HASH_PATH] !== $config[self::FFL_STORE_HASH_PATH]) {
                $this->validateConfigs();
            }
        }
    }

    protected function validateConfigs() 
    {
        if ($this->isValidStoreHash()) {
            $this->messageManager->addSuccess("You are successfully connected to your FFL merchant account!");
        } else {
            $this->messageManager->addError("Unable to connect to your FFL merchant account. Double check your store hash. If the problem persists, please contact support.");
        }
    }

    /**
     * @return bool
     */
    protected function isValidStoreHash() 
    {
        $storeHash = $this->scopeConfig->getValue(self::FFL_STORE_HASH_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $headers = [
            'store-hash' => $storeHash
        ];
        $this->curl->setHeaders($headers);
        $this->curl->get($this->fflSearchUrl);
        $status = $this->curl->getStatus();
        return ($status === 204) ? true : false;
    }
}
