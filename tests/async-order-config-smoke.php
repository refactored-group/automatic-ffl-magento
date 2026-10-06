<?php
// Commerce module registration and asynchronous checkout are separate settings.
namespace Magento\Framework\App {
    class DeploymentConfig {
        public $values = [];
        public function get($path, $default = null) { return $this->values[$path] ?? $default; }
    }
}
namespace Magento\Framework\Module {
    class Manager {
        public $enabled = false;
        public function isEnabled($name) { return $name === 'Magento_AsyncOrder' && $this->enabled; }
    }
}
namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface {
        public function getValue($path, $scope = null, $scopeCode = null);
        public function isSetFlag($path, $scope = null, $scopeCode = null);
    }
}
namespace Magento\Framework\Notification {
    interface MessageInterface { const SEVERITY_CRITICAL = 1; const SEVERITY_MAJOR = 2; }
}
namespace Magento\Store\Model {
    class ScopeInterface { const SCOPE_STORE = 'stores'; }
    interface StoreManagerInterface { public function getStores(); }
}
namespace RefactoredGroup\AutoFflCore\Helper {
    class Data {
        const XML_PATH_IS_ENABLED = 'enabled';
        const XML_PATH_STORE_HASH = 'hash';
        const XML_PATH_STORE_SECRET = 'secret';
    }
}
namespace {
    function __($text) { return $text; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    require __DIR__ . '/../core/Model/AsyncOrderConfig.php';
    require __DIR__ . '/../core/Model/AdminNotification/Configuration.php';

    $modules = new \Magento\Framework\Module\Manager();
    $deployment = new \Magento\Framework\App\DeploymentConfig();
    $asyncOrder = new \RefactoredGroup\AutoFflCore\Model\AsyncOrderConfig($modules, $deployment);
    foreach ([false, true] as $moduleEnabled) {
        $modules->enabled = $moduleEnabled;
        foreach ([null, 0, '0', false, 1, '1', true] as $value) {
            $deployment->values = $value === null ? [] : ['checkout/async'=>$value];
            check($asyncOrder->isEnabled() === ($moduleEnabled && (bool) $value),
                'AsyncOrder requires an enabled module and an enabled checkout/async setting.');
        }
    }

    $config = new class implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public $enabled = true; public $secret = 'fixture-secret';
        public function getValue($path, $scope = null, $scopeCode = null) {
            return $path === 'hash' ? 'fixture-store' : ($path === 'secret' ? $this->secret : null);
        }
        public function isSetFlag($path, $scope = null, $scopeCode = null) { return $this->enabled; }
    };
    $stores = new class implements \Magento\Store\Model\StoreManagerInterface {
        public function getStores() { return [new class { public function getId() { return 7; } }]; }
    };
    $notice = new \RefactoredGroup\AutoFflCore\Model\AdminNotification\Configuration($config, $stores, $asyncOrder);
    $modules->enabled = true; $deployment->values = ['checkout/async'=>0];
    check(!$notice->isDisplayed(), 'An enabled Commerce module alone must not show an unsupported checkout notice.');
    $config->secret = '';
    check($notice->isDisplayed() && strpos($notice->getText(), 'Store Secret') !== false &&
        $notice->getSeverity() === \Magento\Framework\Notification\MessageInterface::SEVERITY_MAJOR,
        'Synchronous checkout must retain the missing-credentials notice and its severity.');
    $config->secret = 'fixture-secret'; $deployment->values = ['checkout/async'=>1];
    check($notice->isDisplayed() && strpos($notice->getText(), 'AsyncOrder') !== false &&
        $notice->getSeverity() === \Magento\Framework\Notification\MessageInterface::SEVERITY_CRITICAL,
        'Actual asynchronous checkout must retain the unsupported-mode notice.');
    $modules->enabled = false;
    check(!$notice->isDisplayed(), 'A disabled or absent AsyncOrder module must not block Open Source checkout.');
    $modules->enabled = true; $config->enabled = false;
    check(!$notice->isDisplayed(), 'Disabled AutoFFL store views must not show compatibility notices.');
    echo "AsyncOrder feature setting and admin notice smoke checks passed.\n";
}
