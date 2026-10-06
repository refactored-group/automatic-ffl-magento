<?php
// Exercise the real metadata writer and repository plugin without Magento bootstrap.
namespace Magento\Framework {
    class DataObject {
        protected $data;
        public function __construct(array $data = []) { $this->data = $data; }
        public function getData($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
        public function setData($key, $value) { $this->data[$key] = $value; return $this; }
        public function __call($name, $arguments) {
            $key = strtolower(preg_replace('/(.)([A-Z])/', '$1_$2', substr($name, 3)));
            if (substr($name, 0, 3) === 'get') return $this->getData($key);
            if (substr($name, 0, 3) === 'set') return $this->setData($key, $arguments[0]);
            throw new \RuntimeException('Unknown method: ' . $name);
        }
    }
}
namespace Magento\Sales\Api {
    interface OrderRepositoryInterface {}
}
namespace Magento\Sales\Api\Data {
    interface OrderInterface {
        public function getExtensionAttributes();
        public function setExtensionAttributes($attributes);
    }
    interface OrderSearchResultInterface { public function getItems(); }
    class OrderExtension extends \Magento\Framework\DataObject {}
    class OrderExtensionFactory {
        public $created = 0;
        public function create() { $this->created++; return new OrderExtension(); }
    }
}
namespace Magento\Sales\Model {
    class Order extends \Magento\Framework\DataObject implements \Magento\Sales\Api\Data\OrderInterface {
        public $history = [];
        private $extension;
        public function getExtensionAttributes() { return $this->extension; }
        public function setExtensionAttributes($attributes) { $this->extension = $attributes; return $this; }
        public function addStatusHistoryComment($comment, $status) {
            $history = new \FixtureHistory($comment, $status);
            $this->history[] = $history;
            return $history;
        }
    }
}
namespace Magento\Sales\Model\Order {
    class Address extends \Magento\Framework\DataObject {}
}
namespace {
    require __DIR__ . '/../core/Model/OrderFflData.php';
    require __DIR__ . '/../core/Model/OrderMetadata.php';
    require __DIR__ . '/../core/Plugin/OrderRepositoryPlugin.php';

    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    class FixtureHistory {
        public $comment;
        public $status;
        public $visible = true;
        public $notified = true;
        public function __construct($comment, $status) { $this->comment = $comment; $this->status = $status; }
        public function setIsVisibleOnFront($value) { $this->visible = $value; return $this; }
        public function setIsCustomerNotified($value) { $this->notified = $value; return $this; }
    }
    class FixtureRepository implements \Magento\Sales\Api\OrderRepositoryInterface {}
    class FixtureSearchResult implements \Magento\Sales\Api\Data\OrderSearchResultInterface {
        public $items;
        public $total = 29;
        public $criteria;
        public function __construct($items) { $this->items = $items; $this->criteria = new \stdClass(); }
        public function getItems() { return $this->items; }
    }

    $license = '5-75-121-07-6L-00199';
    $ezCheck = 'https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=5&licsDis=75&licsSeq=00199';
    $certificate = 'https://certificate.automaticffl.com/11111111-2222-4333-8444-555555555555';
    $snapshot = [
        'version' => 1, 'id' => 921, 'license' => $license,
        'expirationDate' => '2027-12-31', 'uuid' => '11111111-2222-4333-8444-555555555555',
        'company' => 'Fixture Dealer', 'storeHash' => 'private-store-config', 'sandbox' => false,
        'routingState' => 'CA', 'routingStates' => [13 => 'CA']
    ];
    $reader = new \RefactoredGroup\AutoFflCore\Model\OrderFflData();
    $writer = new \RefactoredGroup\AutoFflCore\Model\OrderMetadata($reader);
    $shippingData = [
        'firstname' => 'Jane', 'lastname' => 'Customer', 'company' => 'Fixture Dealer',
        'street' => ['123 Dealer Street', 'Suite 2'], 'city' => 'The Colony',
        'region' => 'Texas', 'region_id' => 57, 'postcode' => '75056',
        'country_id' => 'US', 'telephone' => '5551234567'
    ];
    $shippingAddress = new \Magento\Sales\Model\Order\Address($shippingData);
    $order = new \Magento\Sales\Model\Order(['shipping_address' => $shippingAddress]);
    $merchantNote = $order->addStatusHistoryComment('Merchant delivery instructions', 'processing');
    $writer->apply($order, $snapshot);
    $expectedComment = 'FFL#' . $license . '|Expiration:12/31/2027|EZcheck:' . $ezCheck . '|Certificate:' . $certificate;
    check(count($order->history) === 2 && $order->history[1]->comment === $expectedComment,
        'Dealer order must receive the BigCommerce-compatible comment with the actual expiration date.');
    check($order->history[0] === $merchantNote && $merchantNote->comment === 'Merchant delivery instructions',
        'Existing merchant notes must remain untouched.');
    check($order->history[1]->visible === false && $order->history[1]->notified === false &&
        $order->history[1]->status === false, 'FFL comment must not notify the customer or change status.');
    check(json_decode($order->getFflDealerData(), true) === $snapshot, 'The full attribution snapshot must remain stored.');
    check($order->getShippingAddress() === $shippingAddress && $shippingAddress->getData() === $shippingData,
        'Writing FFL order metadata must preserve the customer name, dealer company, and normal shipping fields without adding FFL data.');
    $writer->apply($order, $snapshot);
    check(count($order->history) === 2, 'Repeated metadata application must not add duplicate comments.');

    $parsed = [];
    foreach (explode('|', $order->history[1]->comment) as $part) {
        if (strpos($part, 'FFL#') === 0) $parsed['license'] = substr($part, 4);
        else {
            list($key, $value) = explode(':', $part, 2);
            $parsed[$key] = $value;
        }
    }
    check($parsed === ['license' => $license, 'Expiration' => '12/31/2027', 'EZcheck' => $ezCheck,
        'Certificate' => $certificate], 'A label-based parser must recover the license, date, and full URLs.');

    $missingSnapshot = $snapshot;
    $missingSnapshot['expirationDate'] = null;
    $missingSnapshot['uuid'] = null;
    $missing = new \Magento\Sales\Model\Order();
    $writer->apply($missing, $missingSnapshot);
    check($missing->history[0]->comment === 'FFL#' . $license . '|Expiration:|EZcheck:' . $ezCheck,
        'Missing date must be empty and missing certificate must be omitted from the comment.');
    foreach (['2027-02-29', '2027-13-01', 'unavailable — verify with eZ Check', "2027-12-31\0", []] as $invalidDate) {
        $invalid = $snapshot; $invalid['expirationDate'] = $invalidDate;
        check($reader->fromSnapshot($invalid)['expiration_date'] === null,
            'Malformed legacy expiration must not become a guessed date or break order retrieval.');
    }
    $invalid = $snapshot; $invalid['uuid'] = '<script>alert(1)</script>'; $invalid['id'] = true;
    $data = $reader->fromSnapshot($invalid);
    check($data['certificate_url'] === null && $data['dealer_id'] === null, 'Invalid dealer ID and UUID must stay unavailable.');
    $invalid['license'] = "bad|Expiration:fake\n";
    check($reader->fromSnapshot($invalid)['license'] === null, 'License must not inject comment fields.');
    check($reader->fromSnapshot($invalid, $license)['license'] === $license, 'A valid saved license must support legacy fallback.');

    $factory = new \Magento\Sales\Api\Data\OrderExtensionFactory();
    $plugin = new \RefactoredGroup\AutoFflCore\Plugin\OrderRepositoryPlugin($factory, $reader);
    $repository = new FixtureRepository();
    $before = $order->getData();
    check($plugin->afterGet($repository, $order) === $order, 'Order identity must be preserved.');
    $expectedFields = [
        'autoffl_license' => $license, 'autoffl_dealer_id' => 921, 'autoffl_expiration_date' => '2027-12-31',
        'autoffl_certificate_url' => $certificate, 'autoffl_ezcheck_url' => $ezCheck
    ];
    check($order->getExtensionAttributes()->getData() === $expectedFields, 'Single order must expose the documented named fields.');
    check($order->getData() === $before, 'Repository reads must not modify the stored snapshot or order data.');
    $extension = $order->getExtensionAttributes();
    $shipping = new \Magento\Framework\DataObject(['address' => $shippingAddress, 'method' => 'flatrate_flatrate']);
    $extension->setShippingAssignments([$shipping]);
    $extension->setThirdPartyReference('preserve-me');
    $plugin->afterGet($repository, $order);
    check($order->getExtensionAttributes() === $extension && $extension->getShippingAssignments() === [$shipping] &&
        $extension->getThirdPartyReference() === 'preserve-me', 'Other modules must keep their existing extension object and values.');

    $legacy = new \Magento\Sales\Model\Order(['ffl_license' => $license, 'ffl_dealer_data' => '{bad-json']);
    $ordinary = new \Magento\Sales\Model\Order();
    $results = new FixtureSearchResult([$ordinary, $order, $legacy, $missing]);
    $criteria = $results->criteria;
    check($plugin->afterGetList($repository, $results) === $results && $results->total === 29 && $results->criteria === $criteria,
        'List hydration must preserve pagination, search criteria, and result identity.');
    check($ordinary->getExtensionAttributes() === null, 'Non-FFL child orders must not inherit dealer fields.');
    $legacyData = $legacy->getExtensionAttributes()->getData();
    check($legacyData['autoffl_license'] === $license && $legacyData['autoffl_ezcheck_url'] === $ezCheck &&
        $legacyData['autoffl_expiration_date'] === null && $legacyData['autoffl_dealer_id'] === null &&
        $legacyData['autoffl_certificate_url'] === null, 'Legacy orders must expose available data without inventing missing metadata.');
    check($missing->getExtensionAttributes()->getAutofflExpirationDate() === null,
        'Unknown expiration must be null in API attributes.');

    $extension->setAutofflLicense('caller-supplied-license');
    $extension->setAutofflExpirationDate('1900-01-01');
    check($plugin->afterSave($repository, $order) === $order && $extension->getAutofflLicense() === $license &&
        $extension->getAutofflExpirationDate() === '2027-12-31' && $order->getData() === $before,
        'Save responses must reflect the stored selection instead of treating API projections as writable dealer data.');
    $fakeExtension = new \Magento\Sales\Api\Data\OrderExtension(['autoffl_license' => $license, 'third_party_reference' => 'keep']);
    $ordinary->setExtensionAttributes($fakeExtension);
    $plugin->afterSave($repository, $ordinary);
    check($fakeExtension->getAutofflLicense() === null && $fakeExtension->getThirdPartyReference() === 'keep',
        'A non-FFL order must not echo caller-supplied dealer fields or erase another module\'s attributes.');

    check($order->getShippingAddress() === $shippingAddress && $shipping->getAddress() === $shippingAddress &&
        $shippingAddress->getData() === $shippingData && $shipping->getMethod() === 'flatrate_flatrate',
        'Order retrieval, list hydration, and save responses must not put FFL metadata inside shipping addresses or assignments.');

    $xml = simplexml_load_file(__DIR__ . '/../core/etc/extension_attributes.xml');
    $fields = [];
    foreach ($xml->xpath('/config/extension_attributes[@for="Magento\\Sales\\Api\\Data\\OrderInterface"]/attribute') as $attribute) {
        $fields[(string) $attribute['code']] = (string) $attribute['type'];
    }
    check($fields === [
        'autoffl_license' => 'string', 'autoffl_dealer_id' => 'int', 'autoffl_expiration_date' => 'string',
        'autoffl_certificate_url' => 'string', 'autoffl_ezcheck_url' => 'string'
    ], 'Generated order extension classes must declare every documented field with its correct type.');
    $di = simplexml_load_file(__DIR__ . '/../core/etc/di.xml');
    $plugins = $di->xpath('/config/type[@name="Magento\\Sales\\Api\\OrderRepositoryInterface"]/plugin');
    check(count($plugins) === 1 && (string) $plugins[0]['type'] === \RefactoredGroup\AutoFflCore\Plugin\OrderRepositoryPlugin::class,
        'Repository plugin must apply to the API service contract.');
    check(!$di->xpath('/config/type[@name="Magento\\Sales\\Block\\Adminhtml\\Order\\View\\Info"]/plugin[@name="display_ffl_license"]'),
        'Admin address formatting must not append FFL metadata.');
    echo "FFL order comment, API metadata, legacy fallback, and clean shipping address smoke checks passed.\n";
}
