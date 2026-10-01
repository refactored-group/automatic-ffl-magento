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
        public $subscribed = true;
        public $states = ['CA'];
        public function getPolicy($storeId) {
            $this->calls++;
            return [
                'apply_ammo_state_rules_in_mixed_carts' => $this->mixedPolicy,
                'firearm_category_ids' => [10],
                'ammo_category_ids' => [20]
            ];
        }
        public function getProducts($storeId, array $descriptors) {
            $this->calls++;
            $this->descriptors = $descriptors;
            $rules = [];
            foreach ($descriptors as $descriptor) {
                // Simulate the older backend's flag-first behavior during a rolling upgrade.
                if ($descriptor['required_ffl'] || in_array(10, $descriptor['categoryIds'], true)) {
                    $rules[] = ['id' => $descriptor['id']];
                } elseif ($this->subscribed && in_array(20, $descriptor['categoryIds'], true)) {
                    $rules[] = ['id' => $descriptor['id'], 'conditions' => [
                        ['type' => 'ship_state', 'states' => $this->states]
                    ]];
                }
            }
            return $rules;
        }
    }
}
namespace {
    require __DIR__ . '/../core/Model/QuoteAnalysis.php';
    require __DIR__ . '/../core/Model/CheckoutRouting.php';

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
        private $categories;
        public function __construct($virtual = false, $forced = false, array $categories = []) {
            $this->virtual = $virtual; $this->forced = $forced; $this->categories = $categories;
        }
        public function isVirtual() { return $this->virtual; }
        public function getRequiredFfl() { return $this->forced; }
        public function getCategoryIds() { return $this->categories; }
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
    $ammo = new Item(13, 100, new Product(false, true, [20]));
    $categoryFirearm = new Item(14, 300, new Product(false, false, [10]));
    $ordinary = new Item(15, 400, new Product());
    $secondAmmo = new Item(16, 500, new Product(false, false, [20]));
    $router = new \RefactoredGroup\AutoFflCore\Model\CheckoutRouting();

    $cases = [
        [[$ordinary], '', 'standard'],
        [[$ammo], '', 'standard'],
        [[$ammo, $secondAmmo, $virtual], '', 'standard'],
        [[$ammo], 'CA', 'standard'],
        [[$ammo], 'CO', 'standard'],
        [[$ammo, $ordinary], '', 'state'],
        [[$ammo, $ordinary], 'CA', 'multishipping'],
        [[$ammo, $ordinary], 'CO', 'standard'],
        [[$firearm, $ammo], '', 'state'],
        [[$firearm, $ammo], 'CA', 'standard'],
        [[$firearm, $ammo], 'CO', 'multishipping'],
        [[$firearm, $ordinary], '', 'multishipping'],
        [[$firearm, $ammo, $ordinary], '', 'multishipping'],
        [[$firearm, $ammo, $ordinary], 'CA', 'multishipping'],
        [[$firearm, $ammo, $ordinary], 'CO', 'multishipping']
    ];
    foreach ($cases as $index => $case) {
        [$caseItems, $state, $route] = $case;
        check($router->decide($analysis->analyze(new \Magento\Quote\Model\Quote($caseItems), $state)) === $route,
            'Checkout route matrix case ' . $index . ' failed.');
    }

    $client->mixedPolicy = false;
    $oldPolicy = new \RefactoredGroup\AutoFflCore\Model\QuoteAnalysis($client, $config);
    check(count($oldPolicy->analyze(new \Magento\Quote\Model\Quote([$firearm, $ammo]), 'CO')['required']) === 1,
        'An old ammo-with-firearms policy must not override the destination state.');

    $result = $analysis->analyze(new \Magento\Quote\Model\Quote([$firearm, $virtual]));
    check($result['allRequired'], 'Virtual items must not make a firearm cart mixed.');
    check(count($client->descriptors) === 1, 'Virtual items must not be classified.');
    check($client->descriptors[0]['required_ffl'], 'An unmatched legacy flag must remain effective.');
    check(!array_key_exists('ffl_type', $client->descriptors[0]), 'The retired type field must not be sent.');

    $result = $analysis->analyze(new \Magento\Quote\Model\Quote([$categoryFirearm]));
    check($result['allRequired'], 'A firearm category must work without the legacy flag.');

    $quote = new \Magento\Quote\Model\Quote([$firearm, $ammo]);
    $analysis = new \RefactoredGroup\AutoFflCore\Model\QuoteAnalysis($client, $config);
    $result = $analysis->analyze($quote, '');
    check($result['unresolved'], 'Ammo without an original destination state is unresolved.');
    check(!$client->descriptors[1]['required_ffl'], 'An ammunition category must take precedence over the old flag.');
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
    check(!$fresh->analyze($quote, '')['unresolved'], 'Known whole-cart dealer delivery needs no state preflight.');
    $result = $fresh->analyze(new \Magento\Quote\Model\Quote([$ammo, $ordinary]), 'CA');
    check($result['allRequired'], 'Ship-all mode must also group restricted ammo with ordinary products.');
    check(!$fresh->analyze(new \Magento\Quote\Model\Quote([$ammo, $ordinary]), 'CO')['required'],
        'Ship-all must not require a dealer for unrestricted ammo without firearms.');

    $config->shipAll = false;
    $client->states = [];
    $emptyStates = new \RefactoredGroup\AutoFflCore\Model\QuoteAnalysis($client, $config);
    check($router->decide($emptyStates->analyze(new \Magento\Quote\Model\Quote([$ammo, $ordinary]))) === 'standard',
        'An empty ammunition state list must not introduce a preflight.');
    $client->subscribed = false;
    $unsubscribed = new \RefactoredGroup\AutoFflCore\Model\QuoteAnalysis($client, $config);
    check($router->decide($unsubscribed->analyze(new \Magento\Quote\Model\Quote([$firearm, $ammo]))) === 'multishipping',
        'Unsubscribed ammo must be treated as ordinary shipping alongside firearms.');

    $calls = $client->calls;
    $result = $fresh->analyze(new \Magento\Quote\Model\Quote([$virtual]));
    check(!$result['required'] && $client->calls === $calls, 'Virtual-only carts must not call the rules API.');
    echo "Quote analysis smoke checks passed.\n";
}
