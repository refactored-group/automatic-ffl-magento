<?php
// A cached assignment must not reapply an old method after dealer-address reset.
namespace Magento\Framework\Event { interface ObserverInterface {} }
namespace {
    require __DIR__ . '/../core/Observer/Checkout/Index.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    class Shipping {
        public $fields = ['country_id' => 'US', 'street' => ['10 Dealer St'], 'shipping_method' => 'flatrate_flatrate'];
        public $ratesRemoved = false;
        public function __call($method, $arguments) {
            $field = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', substr($method, 3)));
            $this->fields[$field] = $arguments[0];
            return $this;
        }
        public function removeAllShippingRates() { $this->ratesRemoved = true; }
    }
    class Assignment {
        public $method = 'flatrate_flatrate'; public $address;
        public function getShipping() { return $this; }
        public function setAddress($address) { $this->address = $address; return $this; }
        public function setMethod($method) { $this->method = $method; return $this; }
    }
    class Quote {
        public $shipping; public $assignment; public $license = 'previous'; public $snapshot = 'previous';
        public $state = 'CA';
        public function getFflLicense() { return $this->license; }
        public function getFflDealerData() { return $this->snapshot; }
        public function getFflRoutingState() { return $this->state; }
        public function setFflRoutingState($value) { $this->state = $value; }
        public function getId() { return 1; }
        public function getShippingAddress() { return $this->shipping; }
        public function setFflLicense($value) { $this->license = $value; }
        public function setFflDealerData($value) { $this->snapshot = $value; }
        public function setTotalsCollectedFlag($value) {}
        public function getExtensionAttributes() { return $this; }
        public function getShippingAssignments() { return [$this->assignment]; }
    }
    class Helper {
        public $ffl = true; public $ammo = false;
        public function isFfl() { return $this->ffl; }
        public function hasConditionalAmmo() { return $this->ammo; }
    }
    class Session { public $quote; public function getQuote() { return $this->quote; } }
    class Repository {
        public $saves = 0;
        public function save($quote) {
            if (!$quote->shipping->fields['country_id'] && $quote->assignment->method) {
                throw new \RuntimeException('The shipping address is missing.');
            }
            $this->saves++;
        }
    }
    $quote = new Quote(); $quote->shipping = new Shipping(); $quote->assignment = new Assignment();
    $session = new Session(); $session->quote = $quote; $repository = new Repository();
    $reflection = new \ReflectionClass(\RefactoredGroup\AutoFflCore\Observer\Checkout\Index::class);
    $observer = $reflection->newInstanceWithoutConstructor();
    $helper = new Helper();
    foreach (['helper' => $helper, 'session' => $session, 'quoteRepository' => $repository] as $name => $value) {
        $reflection->getProperty($name)->setValue($observer, $value);
    }
    $reflection->getMethod('resetFflCheckoutState')->invoke($observer);
    check($repository->saves === 1, 'The cleared dealer quote must save successfully.');
    check($quote->license === null && $quote->snapshot === null, 'A fresh checkout requires a fresh dealer.');
    check($quote->shipping->fields['street'] === [] && $quote->shipping->ratesRemoved, 'Dealer address and rates must reset.');
    check($quote->assignment->method === null && $quote->assignment->address === $quote->shipping,
        'The cached assignment must use the cleared address without an old shipping method.');
    $helper->ffl = false;
    foreach ([false, true] as $ammoRemains) {
        $helper->ammo = $ammoRemains;
        $quote = new Quote(); $quote->shipping = new Shipping(); $quote->assignment = new Assignment();
        $session->quote = $quote;
        $reflection->getMethod('resetFflCheckoutState')->invoke($observer);
        check($quote->license === null && $quote->snapshot === null && $quote->shipping->fields['street'] === [] &&
            $quote->shipping->fields['shipping_method'] === null && $quote->assignment->method === null,
            'Removing the last dealer-required item must clear the previous dealer address and rates.');
        check($quote->state === ($ammoRemains ? 'CA' : null),
            'Keep the shopper destination only when conditional ammunition remains.');
    }
    foreach ([false, true] as $ammoRemains) {
        $helper->ammo = $ammoRemains;
        $quote = new Quote(); $quote->license = null; $quote->snapshot = null;
        $quote->shipping = new Shipping(); $quote->shipping->fields['street'] = ['20 Home St'];
        $quote->assignment = new Assignment(); $session->quote = $quote;
        $reflection->getMethod('resetFflCheckoutState')->invoke($observer);
        check($quote->shipping->fields['street'] === ['20 Home St'] && !$quote->shipping->ratesRemoved &&
            $quote->assignment->method === 'flatrate_flatrate',
            'A genuine shopper address must survive non-dealer checkout.');
        check($quote->state === ($ammoRemains ? 'CA' : null), 'Non-dealer carts retain only relevant routing state.');
    }
    echo "Checkout dealer reset smoke checks passed.\n";
}
