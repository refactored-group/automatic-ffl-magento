<?php

namespace Razoyo\AutomaticFfl\Plugin;

class ConfigPlugin
{
    /**
     * @var string
     */
    const FFL_API_KEY_PATH = 'automaticffl/configuration/api_key';

    /**
     * @var string
     */
    const FFL_IS_ENABLED_PATH = 'automaticffl/configuration/enabled';

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
            self::FFL_API_KEY_PATH => $this->scopeConfig->getValue(self::FFL_API_KEY_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE), 
            self::FFL_IS_ENABLED_PATH => $this->scopeConfig->getValue(self::FFL_IS_ENABLED_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ];
        $proceed();
        $config = $subject->load();

        $this->validateConfigs($oldConfig, $config);
    }

    /**
     * @param array $oldConfig
     * @param array $config
     */
    protected function validateConfigs($oldConfig, $config) 
    {
        $fflApiKey = $this->scopeConfig->getValue(self::FFL_API_KEY_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (isset($config[self::FFL_API_KEY_PATH])) {
            if ($oldConfig[self::FFL_API_KEY_PATH] !== $config[self::FFL_API_KEY_PATH]) {
                if ($this->isValidApiKey($fflApiKey)) {
                    $this->messageManager->addSuccess("You are successfully connected to your FFL merchant account!");
                } else {
                    $this->messageManager->addError("Unable to connect to your FFL merchant account. Double check your API key. If the problem persists, please contact support.");
                }
            }
        }
    }

    /**
     * @param string $apiKey
     * @return bool
     */
    protected function isValidApiKey($apiKey) 
    {
        /*$this->curl->get($url);*/
        return true;
    }
}

