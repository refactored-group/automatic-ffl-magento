<?php
// A small local contract check that runs without a Magento installation.
namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface { public function getValue($path, $scope = null, $scopeCode = null); }
}
namespace Magento\Store\Model {
    class ScopeInterface { const SCOPE_STORE = 'stores'; }
}
namespace Magento\Quote\Model {
    class Quote {
        private $items;
        private $state;
        public function __construct(array $items, $state = '') { $this->items = $items; $this->state = $state; }
        public function getAllVisibleItems() { return $this->items; }
        public function getFflRoutingState() { return $this->state; }
        public function getStoreId() { return 7; }
    }
}
namespace RefactoredGroup\AutoFflCore\Helper {
    class Data {
        const XML_PATH_SANDBOX_MODE = 'sandbox';
        const XML_PATH_STORE_HASH = 'hash';
        const XML_PATH_SHIP_NON_GUN_ITEMS = 'ship_all';
    }
}
namespace RefactoredGroup\AutoFflCore\Model {
    class RestrictionsClient {
        public $calls = 0;
        public $descriptors = [];
        public $mixedPolicy = true;
        public function getPolicy($storeId) {
            $this->calls++;
            return ['apply_ammo_state_rules_in_mixed_carts' => $this->mixedPolicy];
        }
        public function getProducts($storeId, array $descriptors) {
            $this->calls++;
            $this->descriptors = $descriptors;
            $rules = [];
            foreach ($descriptors as $descriptor) {
                if ($descriptor['required_ffl']) {
                    $rules[] = ['id' => $descriptor['id']];
                } elseif ($descriptor['ffl_type'] === 'ammo') {
                    $rules[] = ['id' => $descriptor['id'], 'conditions' => [
                        ['type' => 'ship_state', 'states' => ['CA']]
                    ]];
                }
            }
            return $rules;
        }
    }
}
namespace {
    require __DIR__ . '/../core/Model/QuoteAnalysis.php';

    class Config implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public $shipAll = false;
        public function getValue($path, $scope = null, $scopeCode = null) {
            if ($path === 'hash') return 'fixture-store';
            if ($path === 'ship_all') return $this->shipAll;
            return false;
        }
    }
    class Product {
        private $virtual;
        private $forced;
        private $type;
        public function __construct($virtual = false, $forced = false, $type = '') {
            $this->virtual = $virtual; $this->forced = $forced; $this->type = $type;
        }
        public function isVirtual() { return $this->virtual; }
        public function getRequiredFfl() { return $this->forced; }
        public function getFflType() { return $this->type; }
        public function getCategoryIds() { return []; }
    }
    class Item {
        private $id;
        private $productId;
        private $product;
        public function __construct($id, $productId, Product $product) {
            $this->id = $id; $this->productId = $productId; $this->product = $product;
        }
        public function getId() { return $this->id; }
        public function getProductId() { return $this->productId; }
        public function getProduct() { return $this->product; }
        public function getChildren() { return []; }
    }
    function check($condition, $message) {
        if (!$condition) throw new \RuntimeException($message);
    }

    $client = new \RefactoredGroup\AutoFflCore\Model\RestrictionsClient();
    $config = new Config();
    $analysis = new \RefactoredGroup\AutoFflCore\Model\QuoteAnalysis($client, $config);
    $firearm = new Item(11, 100, new Product(false, true));
    $virtual = new Item(12, 200, new Product(true));
    $ammo = new Item(13, 100, new Product(false, false, 'ammo'));

    $result = $analysis->analyze(new \Magento\Quote\Model\Quote([$firearm, $virtual]));
    check($result['allRequired'], 'Virtual items must not make a firearm cart mixed.');
    check(count($client->descriptors) === 1, 'Virtual items must not be classified.');

    $quote = new \Magento\Quote\Model\Quote([$firearm, $ammo]);
    $result = $analysis->analyze($quote, '');
    check($result['unresolved'], 'Ammo without an original destination state is unresolved.');
    check($client->descriptors[0]['id'] !== $client->descriptors[1]['id'],
        'Separate quote lines with the same product ID need separate restriction IDs.');

    $result = $analysis->analyze($quote, 'CA');
    check(count($result['required']) === 2 && $result['allRequired'],
        'A restricted ammunition state requires both lines in this fixture.');
    $result = $analysis->analyze($quote, 'CO');
    check(count($result['required']) === 1 && !$result['allRequired'],
        'An unrestricted ammunition state leaves the firearm required.');

    $config->shipAll = true;
    $fresh = new \RefactoredGroup\AutoFflCore\Model\QuoteAnalysis($client, $config);
    $result = $fresh->analyze($quote, 'CO');
    check(count($result['required']) === 2, 'Ship-all mode includes all physical lines.');

    $calls = $client->calls;
    $result = $fresh->analyze(new \Magento\Quote\Model\Quote([$virtual]));
    check(!$result['required'] && $client->calls === $calls, 'Virtual-only carts must not call the rules API.');
    echo "Quote analysis smoke checks passed.\n";
}
