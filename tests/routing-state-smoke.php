<?php
namespace Magento\Framework\App\Action {
    interface HttpPostActionInterface {}
    interface HttpGetActionInterface {}
    class Action {
        public $request; public $_url; public $resultRedirectFactory;
        public function getRequest() { return $this->request; }
    }
}
namespace {
    require __DIR__ . '/../core/Controller/Routing/State.php';
    require __DIR__ . '/../core/Controller/Routing/Index.php';
    function __($text) { return $text; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    class Request {
        public $values = [];
        public function getParam($name, $default = null) { return $this->values[$name] ?? $default; }
    }
    class Quote {
        public $state = ''; public $license = 'old'; public $snapshot = 'old'; public $items = true;
        public $shipping;
        public $assignment;
        public function getId() { return 1; }
        public function hasItems() { return $this->items; }
        public function getFflRoutingState() { return $this->state; }
        public function setFflRoutingState($value) { $this->state = $value; }
        public function setFflLicense($value) { $this->license = $value; }
        public function setFflDealerData($value) { $this->snapshot = $value; }
        public function getFflLicense() { return $this->license; }
        public function getFflDealerData() { return $this->snapshot; }
        public function getShippingAddress() { return $this->shipping; }
        public function getExtensionAttributes() { return $this; }
        public function getShippingAssignments() { return [$this->assignment]; }
        public function setTotalsCollectedFlag($value) {}
    }
    class Shipping {
        public $cleared = [];
        public function unsetData($name) { $this->cleared[] = $name; }
        public function setCollectShippingRates($value) {}
    }
    class Assignment {
        public $method = 'flatrate_flatrate'; public $address;
        public function getShipping() { return $this; }
        public function setAddress($address) { $this->address = $address; return $this; }
        public function setMethod($method) { $this->method = $method; return $this; }
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
    class Repository {
        public $saves = 0;
        public function save($quote) {
            if (in_array('country_id', $quote->shipping->cleared, true) && $quote->assignment->method) {
                throw new \RuntimeException('Cannot apply an old shipping method to a cleared address.');
            }
            $this->saves++;
        }
    }
    class Customers { public function isLoggedIn() { return false; } }
    class Helper {
        public $quote; public $available = true; public $entryRoute = 'state'; public $entryAllowed = false;
        public function isEnabled() { return true; }
        public function getCheckoutRoute($quote, $state) { return $state === 'CA' ? 'multishipping' : 'standard'; }
        public function getCheckoutEntryRoute() { return $this->entryRoute; }
        public function allowCheckoutEntry($allowed = true) { $this->entryAllowed = $allowed; }
        public function isMultishippingCheckoutAvailable() { return $this->available; }
        public function isFfl() { return $this->quote->state === 'CA'; }
    }
    class Url { public function getUrl($path) { return '/' . $path; } }
    class Handoff { public $region; public function capture($quote, $address, $region) { $this->region = $region; } }
    $request = new Request(); $quote = new Quote(); $session = new Session(); $session->quote = $quote;
    $quote->shipping = new Shipping();
    $quote->assignment = new Assignment();
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
    check(!$helper->entryAllowed, 'Separate-address routing must not authorize single-address checkout.');
    check($quote->state === 'CA' && $quote->license === null && $quote->snapshot === null,
        'Changing destination must invalidate the old dealer selection.');
    check(in_array('street', $quote->shipping->cleared, true), 'A stale dealer address must not become the home address after changing state.');
    check($quote->assignment->method === null && $quote->assignment->address === $quote->shipping,
        'The cached shipping assignment must not retain the previous dealer method.');
    check($handoff->region === 12, 'A native region ID must resolve to the correct US state.');
    $helper->available = false;
    check($controller->execute()->data['route'] === 'unavailable', 'Unavailable multishipping must not be offered.');
    $request->values = ['state' => 'CO'];
    check($controller->execute()->data['route'] === 'standard', 'Unrestricted mixed carts must remain in ordinary checkout.');
    check($helper->entryAllowed, 'A confirmed single-address destination must authorize the following checkout entry.');
    $request->values = ['state' => 'CA'];
    $controller->execute();
    check(!$helper->entryAllowed, 'A subsequent separate-order response must revoke the previous checkout entry.');
    $quote->items = false;
    check($controller->execute()->status === 409, 'An empty cart must not proceed.');
    $page = new class {
        public $title;
        public function getConfig() { return $this; }
        public function getTitle() { return $this; }
        public function set($title) { $this->title = $title; }
    };
    $pageFactory = new class($page) {
        private $page;
        public function __construct($page) { $this->page = $page; }
        public function create() { return $this->page; }
    };
    $redirectFactory = new class {
        public $path;
        public function create() { return $this; }
        public function setPath($path) { $this->path = $path; return $this; }
    };
    $pageReflection = new \ReflectionClass(\RefactoredGroup\AutoFflCore\Controller\Routing\Index::class);
    $pageController = $pageReflection->newInstanceWithoutConstructor();
    foreach (['helper'=>$helper, 'pages'=>$pageFactory, 'resultRedirectFactory'=>$redirectFactory] as $name=>$value) {
        $pageReflection->getProperty($name)->setValue($pageController, $value);
    }
    check($pageController->execute() === $page && $page->title === 'Shipping destination',
        'The destination controller must allow reselecting a saved ammo state instead of redirecting back to blocked checkout.');
    $helper->entryRoute = 'standard';
    check($pageController->execute() === $redirectFactory && $redirectFactory->path === 'checkout/index',
        'Whole-cart dealer delivery must continue to regular checkout instead of looping through the state screen.');
    echo "Routing state endpoint smoke checks passed.\n";
}
