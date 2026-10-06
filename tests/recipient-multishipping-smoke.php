<?php
namespace Magento\Framework\Exception { class LocalizedException extends \Exception {} }
namespace Magento\Framework\App {
    interface CsrfAwareActionInterface {}
    interface RequestInterface {}
}
namespace Magento\Framework\App\Action { class Action {} }
namespace Magento\Framework\App\Request { class InvalidRequestException extends \Exception {} }
namespace Magento\Multishipping\Controller\Checkout { class AddressesPost {} }
namespace {
    function __($text) { return $text; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    function inject($class, array $fields) {
        $reflection = new \ReflectionClass($class);
        $object = $reflection->newInstanceWithoutConstructor();
        foreach ($fields as $name => $value) $reflection->getProperty($name)->setValue($object, $value);
        return $object;
    }
    class Data {
        public $data;
        public function __construct(array $data = []) { $this->data = $data; }
        public function __call($method, $args) {
            $key = lcfirst(substr($method, 3));
            if (strpos($method, 'set') === 0) { $this->data[$key] = $args[0]; return $this; }
            return $this->data[$key] ?? null;
        }
        public function setCustomAttribute($key, $value) { $this->data[$key] = $value; return $this; }
        public function getCustomAttribute($key) { return new Data(['value' => $this->data[$key] ?? null]); }
    }
    class Addresses {
        public $saved = []; public $stored = [];
        public function save($address) {
            if (!$address->getId()) $address->setId(41);
            $this->saved[] = clone $address;
            $this->stored[$address->getId()] = $address;
            return $address;
        }
        public function getById($id) { return $this->stored[$id]; }
    }
    class Factory { public function create() { return new Data(); } }
    class Request {
        public $data = [];
        public function isPost() { return true; }
        public function getParams() { return $this->data; }
        public function getPost($key, $default = null) { return $this->data[$key] ?? $default; }
        public function getParam($key, $default = null) { return $this->data[$key] ?? $default; }
        public function setParam($key, $value) { $this->data[$key] = $value; return $this; }
        public function setPostValue($key, $value) { $this->data[$key] = $value; return $this; }
    }
    $quote = new Data(['id' => 1, 'isActive' => true, 'customerId' => 9, 'storeId' => 7, 'allShippingAddresses' => []]);
    $request = new Request(); $addresses = new Addresses();
    $helper = new class($quote) {
        private $quote;
        public function __construct($quote) { $this->quote = $quote; }
        public function isEnabled($store) { return true; }
        public function hasFflItem($quote = null) { return $quote !== null; }
        public function getCustomerQuote() { return $this->quote; }
    };
    $dealer = ['id' => '921', 'license' => 'fixture-license', 'firstName' => 'Dealer', 'lastName' => 'Contact',
        'address1' => '10 Main St', 'address2' => '', 'city' => 'Denver', 'state' => 'CO',
        'postalCode' => '80202', 'company' => 'Dealer Business', 'phone' => '5551231234'];
    require __DIR__ . '/../core/Model/RecipientName.php';
    require __DIR__ . '/../checkout-multishipping/Model/DealerGrouping.php';
    require __DIR__ . '/../checkout-multishipping/Model/Recipient.php';
    require __DIR__ . '/../checkout-multishipping/Controller/Index/Index.php';
    require __DIR__ . '/../checkout-multishipping/Plugin/Multishipping/Controller/Checkout/AddressesPost.php';
    $controller = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Controller\Index\Index::class, [
        '_rawFactory' => new Factory(), 'request' => $request,
        'customerSession' => new Data(['customerId' => 9, 'customer' => new Data()]), 'checkoutSession' => new Data(['quote' => $quote]),
        'helper' => $helper, 'addressRepository' => $addresses, 'addressDataFactory' => new Factory(),
        'quoteRepository' => new class { public function save($quote) {} },
        'resourceConnection' => new class {
            public function getConnection() { return $this; }
            public function beginTransaction() {}
            public function commit() {}
            public function rollBack() {}
        },
        'snapshot' => new class($dealer) {
            private $dealer;
            public function __construct($dealer) { $this->dealer = $dealer; }
            public function fromJson($data, $license, $store) { return $this->dealer; }
        },
        'regionCollectionFactory' => new class {
            public function create() { return $this; }
            public function addFieldToFilter($field, $value) { return $this; }
            public function getFirstItem() { return $this; }
            public function getDataByKey($key) { return 13; }
        }
    ]);
    $addresses->stored[10] = new Data(['id'=>10,'customerId'=>9,'firstname'=>'  Zoë ', 'lastname'=>"O'Neil-Smith"]);
    $save = function () use ($controller) {
        return (new \ReflectionMethod($controller, 'saveSelection'))->invoke($controller, new Data());
    };
    $request->data = ['recipient_address_id'=>10,'recipient_first_name'=>'Untrusted','recipient_last_name'=>'Override'];
    $save();
    check($addresses->stored[41]->getFirstname() === 'Zoë', 'Multishipping dealer creation must use the shopper first name.');
    check($addresses->stored[41]->getLastname() === "O'Neil-Smith", 'Dealer contact names must not replace shopper names.');
    check($addresses->stored[41]->getCompany() === 'Dealer Business', 'Dealer company must stay on the address.');
    $addresses->stored[10]->setLastname(" \t ");
    try { $save(); throw new \RuntimeException('Blank recipient was accepted.'); }
    catch (\Magento\Framework\Exception\LocalizedException $error) { check(strpos($error->getMessage(), 'name') !== false, 'A missing recipient must be explained.'); }
    check(count($addresses->saved) === 1, 'Invalid recipient must not create a dealer address.');
    $addresses->stored[10]->setLastname("O'Neil-Smith");
    $addresses->stored[10]->setCustomerId(11);
    try { $save(); throw new \RuntimeException('Foreign saved address accepted.'); }
    catch (\Magento\Framework\Exception\LocalizedException $error) { check(strpos($error->getMessage(), 'saved shipping address') !== false, 'Foreign recipient sources must be rejected.'); }
    $addresses->stored[10]->setCustomerId(9);

    $shipping = new Data(['customerAddressId' => 41, 'firstname' => 'Zoë', 'lastname' => "O'Neil-Smith"]);
    $quote->setAllShippingAddresses([$shipping]);
    $messages = new class { public $errors = []; public function addErrorMessage($message) { $this->errors[] = $message; } };
    $context = new Data(['request' => $request, 'messageManager' => $messages, 'response' => new Data(),
        'url' => new class { public function getUrl($path) { return $path; } }]);
    $plugin = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout\AddressesPost::class,
        ['helper' => $helper, 'context' => $context, 'addresses' => $addresses]);
    $request->data = ['ffl_recipient' => [['address_id' => 41, 'firstname' => 'Edited', 'lastname' => 'Customer']]];
    $proceeded = false;
    $plugin->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(), function () use (&$proceeded, $shipping) {
        check($shipping->getFirstname() === 'Edited', 'Native continuation must use the edited recipient.');
        $proceeded = true;
    });
    check($proceeded && $addresses->stored[41]->getLastname() === 'Customer', 'Edited names must reach the saved dealer address.');
    $request->data['ffl_recipient'][0]['lastname'] = '';
    $proceeded = false;
    $plugin->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(), function () use (&$proceeded) { $proceeded = true; });
    check(!$proceeded && count($messages->errors) === 1, 'Blank name must block native continuation.');
    $request->data['ffl_recipient'][0] = ['address_id' => 42, 'firstname' => 'Other', 'lastname' => 'Person'];
    $plugin->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(), function () use (&$proceeded) { $proceeded = true; });
    check(!$proceeded && count($messages->errors) === 2, 'An address outside the cart dealer snapshot must never be edited.');
    $request->data['ffl_recipient'][0]['address_id'] = 41;
    $addresses->stored[41]->setCustomerId(10);
    $plugin->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(), function () use (&$proceeded) { $proceeded = true; });
    check(!$proceeded && count($messages->errors) === 3, 'Another customer dealer address must never be edited.');
    $addresses->stored[41]->setCustomerId(9);
    $request->data = ['ffl_recipient' => [['address_id'=>41,'firstname'=>'Deliberate','lastname'=>'Recipient','override'=>1]]];
    $plugin->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(), function () {});
    check($addresses->stored[41]->getFirstname()==='Deliberate' && $addresses->stored[10]->getFirstname()==='  Zoë ',
        'Change Name must affect the dealer shipment, never the saved home address.');
    check(json_decode($quote->getFflDealerData(),true)['recipient']['lastname']==='Recipient',
        'An explicit shared recipient must survive checkout reloads and dealer changes.');
    $account = new Data(['firstname'=>'Account','lastname'=>'Customer']);
    check(\RefactoredGroup\AutoFflCore\Model\RecipientName::fromSources(null,$account)['firstname']==='Account',
        'A firearms-only cart without a saved recipient must fall back to its signed-in account.');
    check(\RefactoredGroup\AutoFflCore\Model\RecipientName::fromSources(new Data(['firstname'=>'','lastname'=>'Missing']),$account)['firstname']==='Account',
        'An incomplete saved name must fall back as a complete pair.');
    check(\RefactoredGroup\AutoFflCore\Model\RecipientName::fromSources(null,new Data())===['firstname'=>'','lastname'=>''],
        'Missing saved and account names must remain blank so the required editor is shown.');
    echo "Multishipping recipient creation, edits, and address ownership checks passed.\n";
}
