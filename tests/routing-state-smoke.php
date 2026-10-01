<?php
namespace Magento\Framework\App\Action {
    interface HttpPostActionInterface {}
    class Action {
        public $request; public $_url;
        public function getRequest() { return $this->request; }
    }
}
namespace {
    require __DIR__ . '/../core/Controller/Routing/State.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    class Request {
        public $values = [];
        public function getParam($name, $default = null) { return $this->values[$name] ?? $default; }
    }
    class Quote {
        public $state = ''; public $license = 'old'; public $snapshot = 'old'; public $items = true;
        public $shipping;
        public function getId() { return 1; }
        public function hasItems() { return $this->items; }
        public function getFflRoutingState() { return $this->state; }
        public function setFflRoutingState($value) { $this->state = $value; }
        public function setFflLicense($value) { $this->license = $value; }
        public function setFflDealerData($value) { $this->snapshot = $value; }
        public function getFflLicense() { return $this->license; }
        public function getFflDealerData() { return $this->snapshot; }
        public function getShippingAddress() { return $this->shipping; }
        public function setTotalsCollectedFlag($value) {}
    }
    class Shipping {
        public $cleared = [];
        public function unsetData($name) { $this->cleared[] = $name; }
        public function setCollectShippingRates($value) {}
    }
    class Session { public $quote; public function getQuote() { return $this->quote; } }
    class Validator { public $valid = true; public function validate($request) { return $this->valid; } }
    class Result {
        public $status = 200; public $data;
        public function setHttpResponseCode($code) { $this->status = $code; return $this; }
        public function setData($data) { $this->data = $data; return $this; }
    }
    class Factory { public function create() { return new Result(); } }
    class Region {
        public $code;
        public function getId() { return $this->code === 'CA' ? 12 : ($this->code === 'CO' ? 13 : 0); }
        public function getCode() { return $this->code; }
    }
    class Regions {
        public $filters = [];
        public function create() { return new self(); }
        public function addFieldToFilter($name, $value) { $this->filters[$name] = $value['eq']; return $this; }
        public function getFirstItem() {
            $region = new Region();
            $region->code = $this->filters['code'] ?? ([12 => 'CA', 13 => 'CO'][$this->filters['region_id']] ?? '');
            return $region;
        }
    }
    class Repository { public $saves = 0; public function save($quote) { $this->saves++; } }
    class Customers { public function isLoggedIn() { return false; } }
    class Helper {
        public $quote; public $available = true;
        public function isEnabled() { return true; }
        public function getCheckoutRoute($quote, $state) { return $state === 'CA' ? 'multishipping' : 'standard'; }
        public function isMultishippingCheckoutAvailable() { return $this->available; }
        public function isFfl() { return $this->quote->state === 'CA'; }
    }
    class Url { public function getUrl($path) { return '/' . $path; } }
    class Handoff { public $region; public function capture($quote, $address, $region) { $this->region = $region; } }
    $request = new Request(); $quote = new Quote(); $session = new Session(); $session->quote = $quote;
    $quote->shipping = new Shipping();
    $validator = new Validator(); $helper = new Helper(); $helper->quote = $quote;
    $repository = new Repository(); $handoff = new Handoff();
    $reflection = new \ReflectionClass(\RefactoredGroup\AutoFflCore\Controller\Routing\State::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    foreach (['request' => $request, '_url' => new Url(), 'session' => $session, 'validator' => $validator,
        'helper' => $helper, 'regions' => new Regions(), 'quotes' => $repository,
        'jsonFactory' => new Factory(), 'customers' => new Customers(), 'handoff' => $handoff] as $name => $value) {
        $reflection->getProperty($name)->setValue($controller, $value);
    }
    $validator->valid = false;
    check($controller->execute()->status === 403 && !$repository->saves, 'A bad form key must not mutate the quote.');
    $validator->valid = true; $request->values = ['state' => 'ZZ'];
    check($controller->execute()->status === 422 && !$repository->saves, 'An unknown US state must not mutate the quote.');
    $request->values = ['state' => '', 'address' => json_encode(['countryId' => 'US', 'regionId' => 12])];
    $response = $controller->execute();
    check($response->data['route'] === 'multishipping' && $response->data['requiresLogin'], 'Restricted mixed carts must explain native guest authentication.');
    check($quote->state === 'CA' && $quote->license === null && $quote->snapshot === null,
        'Changing destination must invalidate the old dealer selection.');
    check(in_array('street', $quote->shipping->cleared, true), 'A stale dealer address must not become the home address after changing state.');
    check($handoff->region === 12, 'A native region ID must resolve to the correct US state.');
    $helper->available = false;
    check($controller->execute()->data['route'] === 'unavailable', 'Unavailable multishipping must not be offered.');
    $request->values = ['state' => 'CO'];
    check($controller->execute()->data['route'] === 'standard', 'Unrestricted mixed carts must remain in ordinary checkout.');
    $quote->items = false;
    check($controller->execute()->status === 409, 'An empty cart must not proceed.');
    echo "Routing state endpoint smoke checks passed.\n";
}
