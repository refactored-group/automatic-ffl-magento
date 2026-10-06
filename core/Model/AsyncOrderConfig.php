<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Module\Manager as ModuleManager;

class AsyncOrderConfig
{
    private $modules;
    private $deploymentConfig;

    public function __construct(ModuleManager $modules, DeploymentConfig $deploymentConfig)
    {
        $this->modules = $modules;
        $this->deploymentConfig = $deploymentConfig;
    }

    public function isEnabled(): bool
    {
        return $this->modules->isEnabled('Magento_AsyncOrder') &&
            (bool) $this->deploymentConfig->get('checkout/async', false);
    }
}
