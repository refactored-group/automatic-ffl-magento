<?php
// Follow checkout redirects into the cart without losing unrelated messages.
namespace Magento\Framework\Event {
    interface ObserverInterface {}
    class Observer {
        private $eventName;
        private $response;
        public function __construct($name, $response) { $this->eventName = $name; $this->response = $response; }
        public function getEvent() { return $this; }
        public function getName() { return $this->eventName; }
        public function getControllerAction() { return $this; }
        public function getResponse() { return $this->response; }
    }
}
namespace Magento\Framework\Message { interface MessageInterface { const TYPE_ERROR = 'error'; } }
namespace Magento\Framework\App\Action { class Action { const FLAG_NO_DISPATCH = 'no-dispatch'; } }
namespace {
    function __($text) { return $text; }
    function check($value, $message) { if (!$value) throw new \RuntimeException($message); }
    require __DIR__ . '/../core/Observer/Checkout/Index.php';

    class Notice implements \Magento\Framework\Message\MessageInterface {
        public $type;
        public $text;
        public function __construct($type) { $this->type = $type; }
        public function setText($text) { $this->text = (string) $text; return $this; }
    }
    class Messages {
        public $items = [];
        public function createMessage($type) { return new Notice($type); }
        public function addErrorMessage($text) { $this->items[] = $this->createMessage('error')->setText($text); }
        public function addUniqueMessages(array $messages) {
            foreach ($messages as $message) {
                if (!in_array($message, $this->items, false)) $this->items[] = $message;
            }
        }
    }
    $helper = new class {
        public $enabled = true;
        public $route = 'multishipping';
        public $available = false;
        public $conditionalAmmo = false;
        public $ordinaryItems = false;
        public $entryAllowed = false;
        public function isEnabled() { return $this->enabled; }
        public function getCheckoutRoute() { return $this->route; }
        public function getCheckoutEntryRoute() {
            return $this->conditionalAmmo && !$this->ordinaryItems ? 'state' : $this->route;
        }
        public function consumeCheckoutEntry() {
            $allowed = $this->entryAllowed; $this->entryAllowed = false; return $allowed;
        }
        public function isFfl() { return false; }
        public function hasConditionalAmmo() { return $this->conditionalAmmo; }
        public function isMixedCart() { return $this->route === 'multishipping'; }
        public function isMultishippingCheckoutAvailable() { return $this->available; }
        public function shipNonGunItems() { return false; }
    };
    $messages = new Messages();
    $messages->addErrorMessage('Unrelated cart error.');
    $response = new class {
        public $redirect;
        public function setRedirect($url) { $this->redirect = $url; return $this; }
    };
    $flags = new class {
        public $values = [];
        public function set($controller, $flag, $value) { $this->values[$flag] = $value; }
    };
    $url = new class { public function getUrl($path) { return '/' . $path; } };
    $reflection = new \ReflectionClass(\RefactoredGroup\AutoFflCore\Observer\Checkout\Index::class);
    $observer = $reflection->newInstanceWithoutConstructor();
    foreach (['helper'=>$helper, 'messageManager'=>$messages, 'url'=>$url, 'actionFlag'=>$flags] as $key=>$value) {
        $reflection->getProperty($key)->setValue($observer, $value);
    }
    $session = new class { public function getQuote() { return null; } };
    $reflection->getProperty('session')->setValue($observer, $session);
    $checkoutEvent = new \Magento\Framework\Event\Observer('controller_action_predispatch_checkout_index_index', $response);
    $cartEvent = new \Magento\Framework\Event\Observer('controller_action_predispatch_checkout_cart_index', $response);
    $observer->execute($checkoutEvent);
    $observer->execute($cartEvent);
    check($response->redirect === '/checkout/cart' && $flags->values['no-dispatch'],
        'Unavailable multi-address checkout must redirect to the cart and stop normal checkout.');
    check(count($messages->items) === 2 && $messages->items[0]->text === 'Unrelated cart error.',
        'Checkout followed by cart must show exactly one FFL notice and preserve unrelated errors.');
    check($messages->items[1]->text === 'Some of your items require shipment to an FFL dealer. You will need to order them separately.',
        'The cart must use the approved separate-order wording.');
    $observer->execute($cartEvent);
    $observer->execute($checkoutEvent);
    $observer->execute($cartEvent);
    check(count($messages->items) === 2, 'Repeated cart visits or checkout attempts must not stack the FFL notice.');

    $messages->items = [];
    $helper->conditionalAmmo = true;
    $observer->execute($checkoutEvent);
    check($response->redirect === '/autoffl/routing/index' && !$messages->items,
        'A saved unrestricted ammo state must let the shopper choose a destination again instead of blocking checkout.');
    $helper->available = true;
    $observer->execute($checkoutEvent);
    check($response->redirect === '/autoffl/routing/index' && !$messages->items,
        'Mixed ammo carts must choose the destination before available multishipping as well.');
    $helper->route = 'standard';
    $observer->execute($checkoutEvent);
    check($response->redirect === '/autoffl/routing/index',
        'A saved dealer-required ammo state must reopen the prompt after a firearm is added.');
    $helper->entryAllowed = true;
    $response->redirect = null; $flags->values = [];
    $observer->execute($checkoutEvent);
    check($response->redirect === null && !$flags->values && !$helper->entryAllowed,
        'The confirmed destination must enter native checkout once without a redirect loop.');
    $observer->execute($checkoutEvent);
    check($response->redirect === '/autoffl/routing/index',
        'Returning from the cart to checkout must require a fresh destination confirmation.');
    $helper->route = 'multishipping';
    $helper->ordinaryItems = true;
    $observer->execute($checkoutEvent);
    check($response->redirect === '/multishipping/checkout' && !$messages->items,
        'Firearms, ammo, and ordinary products must go directly to available multishipping.');
    $helper->available = false;
    $observer->execute($checkoutEvent);
    $observer->execute($cartEvent);
    check($response->redirect === '/checkout/cart' && count($messages->items) === 1 &&
        $messages->items[0]->text === 'Some of your items require shipment to an FFL dealer. You will need to order them separately.',
        'The same cart must show one separate-orders message when multishipping is disabled.');
    $messages->items = [];
    $helper->ordinaryItems = false;
    $helper->conditionalAmmo = false;
    $helper->available = true;
    $observer->execute($checkoutEvent);
    check($response->redirect === '/multishipping/checkout' && !$messages->items,
        'Available multi-address checkout must remain accessible without a separate-order error.');
    $helper->route = 'state';
    $observer->execute($checkoutEvent);
    check($response->redirect === '/autoffl/routing/index' && !$messages->items,
        'The pre-checkout state screen must remain accessible without an error banner.');
    $helper->route = 'standard';
    $observer->execute($cartEvent);
    check(!$messages->items, 'Ordinary carts must not receive an FFL notice.');
    $helper->enabled = false;
    $helper->route = 'multishipping';
    $observer->execute($cartEvent);
    check(!$messages->items, 'Disabled AutoFFL must not add a notice.');
    echo "Single cart notice, repeated redirects, unrelated errors, and enabled/disabled checkout branches passed.\n";
}
