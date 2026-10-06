<?php
namespace Magento\Quote\Model { class Quote {} }
namespace Magento\Multishipping\Block\Checkout {
    class Addresses
    {
        public $checkout;
        protected $_filterGridFactory;
        public function getCheckout() { return $this->checkout; }
    }
}
namespace Magento\Framework\Filter {
    class Sprintf { public function __construct($format) {} }
}
namespace Magento\Checkout\Controller\Index { class Index {} }
namespace {
    function check($condition, $message)
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function inject($class, array $fields)
    {
        $reflection = new \ReflectionClass($class);
        $object = $reflection->newInstanceWithoutConstructor();
        foreach ($fields as $name => $value) {
            $reflection->getProperty($name)->setValue($object, $value);
        }
        return $object;
    }

    class Record
    {
        public $data;
        public function __construct(array $data = []) { $this->data = $data; }
        public function __call($method, $arguments)
        {
            $key = lcfirst(substr($method, 3));
            if (strpos($method, 'set') === 0) {
                $this->data[$key] = $arguments[0];
                return $this;
            }
            return $this->data[$key] ?? null;
        }
    }

    class SessionBag
    {
        public $data = [];
        public function setData($key, $value) { $this->data[$key] = $value; return $this; }
        public function unsetData($key) { unset($this->data[$key]); return $this; }
        public function getData($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
    }

    class FixtureQuote extends Record
    {
        public $items = [];
        public function getAllVisibleItems() { return $this->items; }
    }

    require __DIR__ . '/../checkout-multishipping/Model/Quote.php';
    require __DIR__ . '/../checkout-multishipping/Block/Checkout/Addresses.php';
    require __DIR__ . '/../checkout-multishipping/Plugin/Checkout/Controller/Index/Index.php';

    $model = (new \ReflectionClass(
        \RefactoredGroup\AutoFflCheckoutMultiShipping\Model\Quote::class
    ))->newInstanceWithoutConstructor();
    $recoverModelQty = new \ReflectionMethod($model, 'fillMissingAddressItemQty');
    $addressItem = new Record(['qty' => null, 'quoteItem' => new Record(['qty' => 3])]);
    $recoverModelQty->invoke($model, $addressItem);
    check($addressItem->getQty() === 3, 'The quote model must recover a missing address-item quantity.');
    $addressItem->setQty(1);
    $recoverModelQty->invoke($model, $addressItem);
    check($addressItem->getQty() === 1, 'The quote model must preserve a valid split quantity.');

    $quote = new FixtureQuote();
    $quoteItem = new Record(['id' => 11, 'qty' => 2]);
    $quote->items = [$quoteItem];
    $checkout = new Record(['quote' => $quote]);
    $helper = new class {
        public function getMultishippingItemAddress($quote, $item) { return null; }
        public function multishippingRoutingState($quote, $address, $item) { return ''; }
        public function isFflItem($item, $quote, $state) { return false; }
        public function isConditionalAmmoItem($item, $quote) { return false; }
    };
    $grid = new class {
        public function create() { return $this; }
        public function addFilter($filter, $field) { return $this; }
        public function filter($items) { return $items; }
    };
    $block = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout\Addresses::class, [
        'autoFflHelper' => $helper,
        '_filterGridFactory' => $grid
    ]);
    $block->checkout = $checkout;

    $checkout->setQuoteShippingAddressesItems([
        new Record(['quoteItemId' => 11, 'quoteItem' => $quoteItem, 'qty' => null]),
        new Record(['quoteItemId' => 11, 'quoteItem' => $quoteItem, 'qty' => null])
    ]);
    $rows = $block->getItems();
    check(count($rows) === 1 && $rows[0]->getQty() === 2,
        'Missing per-unit quantities must fall back once after grouping.');

    $checkout->setQuoteShippingAddressesItems([
        new Record(['quoteItemId' => 11, 'quoteItem' => $quoteItem, 'qty' => 1]),
        new Record(['quoteItemId' => 11, 'quoteItem' => $quoteItem, 'qty' => null])
    ]);
    $rows = $block->getItems();
    check($rows[0]->getQty() === 1,
        'A known split quantity must not expand to the full quote quantity.');

    $routeHelper = new class {
        public $route = 'standard';
        public $available = false;
        public $mixed = false;
        public function getCheckoutRoute() { return $this->route; }
        public function isMultishippingCheckoutAvailable() { return $this->available; }
        public function isMixedCart() { return $this->mixed; }
    };
    $msHelper = new class {
        public $clears = 0;
        public function clearCustomerSession() { $this->clears++; }
    };
    $session = new SessionBag();
    $redirectFactory = new class {
        public function create() {
            return new class {
                public $path;
                public function setPath($path) { $this->path = $path; return $this; }
            };
        }
    };
    $plugin = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Checkout\Controller\Index\Index::class, [
        'resultRedirectFactory' => $redirectFactory,
        'helper' => $routeHelper,
        'msHelper' => $msHelper,
        'customerSession' => $session
    ]);
    $controller = new \Magento\Checkout\Controller\Index\Index();
    $proceeded = 0;
    $next = function () use (&$proceeded) { $proceeded++; return 'native'; };

    $session->setData('ffl_checkout_button_clicked', 'proceed_to_checkout');
    check($plugin->aroundExecute($controller, $next) === 'native' && $proceeded === 1 &&
        $session->getData('ffl_checkout_button_clicked') === null,
        'Normal checkout must clear a stale grouped-multishipping flag.');

    $routeHelper->available = true;
    $routeHelper->mixed = true;
    $redirect = $plugin->aroundExecute($controller, $next);
    check($redirect->path === 'multishipping/checkout' && $proceeded === 1 &&
        $session->getData('ffl_checkout_button_clicked') === 'proceed_to_checkout',
        'Only an automatic mixed-cart redirect may set the grouped-multishipping flag.');

    $routeHelper->route = 'state';
    $redirect = $plugin->aroundExecute($controller, $next);
    check($redirect->path === 'autoffl/routing/index' && $proceeded === 1,
        'The ammunition destination route must still run before checkout.');

    echo "Checkout compatibility protections passed.\n";
}
