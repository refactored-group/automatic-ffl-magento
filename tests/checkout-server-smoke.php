<?php
// Exercise the actual shipping and placement plugins when browser routing is bypassed.
namespace Magento\Framework\Exception { class LocalizedException extends \Exception {} }
namespace Magento\Checkout\Api\Data { interface ShippingInformationInterface {} }
namespace Magento\Checkout\Model { class ShippingInformationManagement {} }
namespace Magento\Quote\Model {
    class Quote {
        public $state = 'CO'; public $multi = false; public $snapshot = null;
        public function getStoreId() { return 7; }
        public function getFflRoutingState() { return $this->state; }
        public function setFflRoutingState($value) { $this->state = $value; }
        public function setFflLicense($value) {}
        public function setFflDealerData($value) { $this->snapshot = $value; }
        public function getFflDealerData() { return $this->snapshot; }
        public function getIsMultiShipping() { return $this->multi; }
    }
}
namespace Magento\Sales\Model {
    class Order {
        public $quote; public $shipping; public $license = null; public $snapshot = null;
        public $items;
        public function getStoreId() { return 7; }
        public function getQuote() { return $this->quote; }
        public function getShippingAddress() { return $this->shipping; }
        public function getFflLicense() { return $this->license; }
        public function getFflDealerData() { return $this->snapshot; }
        public function getAllVisibleItems() { return $this->items ?: [new \FixtureOrderItem()]; }
    }
}
namespace {
    function __($text) { return $text; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    function inject($class, array $fields) {
        $reflection = new \ReflectionClass($class);
        $object = $reflection->newInstanceWithoutConstructor();
        foreach ($fields as $name => $value) {
            $reflection->getProperty($name)->setValue($object, $value);
        }
        return $object;
    }
    function rejects($call, $fragment) {
        try { $call(); } catch (\Magento\Framework\Exception\LocalizedException $error) {
            check(strpos($error->getMessage(), $fragment) !== false, 'Wrong rejection: ' . $error->getMessage());
            return;
        }
        throw new \RuntimeException('Expected server rejection: ' . $fragment);
    }
    class FixtureOrderItem {
        private $id;
        public function __construct($id = 13) { $this->id = $id; }
        public function getQuoteItemId() { return $this->id; }
    }
    class FixtureAddress { public $state = 'CA'; }
    class FixtureHelper {
        public function isEnabled($store = null) { return true; }
        public function getAddressState($address) { return $address->state; }
    }
    class FixtureAnalysis {
        public $lastState;
        public $withFirearm = false;
        public function analyze($quote, $state = null) {
            $state = $state === null ? $quote->state : $state;
            $this->lastState = $state;
            $required = $state === 'CA' ? [new \FixtureItem()] : [];
            if ($this->withFirearm) $required[] = new \FixtureItem(12);
            return ['required' => $required,
                'allRequired' => false, 'unresolved' => $state === '',
                'ammo' => [['items' => [new \FixtureItem()]]]];
        }
    }
    class FixtureItem {
        private $id;
        public function __construct($id = 13) { $this->id = $id; }
        public function getId() { return $this->id; }
    }
    class FixtureRepository {
        public $quote;
        public function getActive($id) { return $this->quote; }
    }
    class FixtureShipping implements \Magento\Checkout\Api\Data\ShippingInformationInterface {
        public $address;
        public function getExtensionAttributes() { return null; }
        public function getShippingAddress() { return $this->address; }
    }
    require __DIR__ . '/../core/Model/Checkout/ShippingInformationManagement.php';
    require __DIR__ . '/../core/Plugin/ModelOrderPlugin.php';
    $quote = new \Magento\Quote\Model\Quote();
    $repository = new FixtureRepository(); $repository->quote = $quote;
    $helper = new FixtureHelper(); $analysis = new FixtureAnalysis();
    $shippingPlugin = inject(\RefactoredGroup\AutoFflCore\Model\Checkout\ShippingInformationManagement::class,
        ['quoteRepository' => $repository, 'autoFflHelper' => $helper, 'analysis' => $analysis]);
    $information = new FixtureShipping(); $information->address = new FixtureAddress();
    rejects(function () use ($shippingPlugin, $information) {
        $shippingPlugin->beforeSaveAddressInformation(new \Magento\Checkout\Model\ShippingInformationManagement(), 1, $information);
    }, 'separate shipping addresses');
    check($quote->state === 'CA', 'The actual changed shipping state must replace the old cart state.');
    $information->address->state = 'CO';
    $shippingPlugin->beforeSaveAddressInformation(new \Magento\Checkout\Model\ShippingInformationManagement(), 1, $information);
    $quote->state = ''; $information->address->state = '';
    rejects(function () use ($shippingPlugin, $information) {
        $shippingPlugin->beforeSaveAddressInformation(new \Magento\Checkout\Model\ShippingInformationManagement(), 1, $information);
    }, 'delivery state');

    $placement = inject(\RefactoredGroup\AutoFflCore\Plugin\ModelOrderPlugin::class,
        ['helper' => $helper, 'analysis' => $analysis]);
    $order = new \Magento\Sales\Model\Order(); $order->quote = $quote; $order->shipping = new FixtureAddress();
    $quote->state = 'CO';
    rejects(function () use ($placement, $order) { $placement->beforePlace($order); }, 'separate shipping addresses');
    check($analysis->lastState === 'CA', 'Placement must recheck the actual home destination instead of trusting the old preflight state.');
    $quote->multi = true; $quote->snapshot = json_encode(['addresses' => []]);
    $order->shipping->state = 'CO';
    $placement->beforePlace($order);
    $order->shipping->state = 'CA';
    rejects(function () use ($placement, $order) { $placement->beforePlace($order); }, 'licensed dealer');
    $analysis->withFirearm = true; $order->shipping->state = '';
    $order->items = [new FixtureOrderItem(12), new FixtureOrderItem(13)];
    rejects(function () use ($placement, $order) { $placement->beforePlace($order); }, 'delivery state');
    echo "Checkout server guard smoke checks passed.\n";
}
