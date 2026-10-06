<?php
namespace Magento\Framework\App\Helper { class AbstractHelper {
    protected function _getUrl($path) { return '/' . $path; }
} }
namespace Magento\Framework\View\Element { class Template {
    public $nameInLayout = 'autoffl.cart.destination';
    public function getNameInLayout() { return $this->nameInLayout; }
} }
namespace Magento\Framework\Exception { class LocalizedException extends \Exception {} }
namespace Magento\Customer\Api\Data { interface AddressInterface {} }
namespace Magento\Framework\View\Element\Block { interface ArgumentInterface {} }
namespace Magento\Multishipping\Controller\Checkout { class AddressesPost {} }
namespace Magento\Framework\Controller { class ResultFactory { const TYPE_JSON = 'json'; } }
namespace Magento\Framework\Filter { class Sprintf { public function __construct($format) {} } }
namespace Magento\Multishipping\Block\Checkout { class Addresses {
    public $checkout;
    protected $_filterGridFactory;
    public function getCheckout() { return $this->checkout; }
} }
namespace {
    function __($text, ...$args) { foreach ($args as $i => $arg) $text = str_replace('%'.($i+1), $arg, $text); return $text; }
    function check($value, $message) { if (!$value) throw new \RuntimeException($message); }
    function inject($class, $fields) {
        $r = new \ReflectionClass($class); $object = $r->newInstanceWithoutConstructor();
        foreach ($fields as $key => $value) $r->getProperty($key)->setValue($object, $value);
        return $object;
    }
    class Record {
        public $data;
        public function __construct($data = []) { $this->data = $data; }
        public function __call($method, $args) {
            $key = lcfirst(substr($method, 3));
            if (strpos($method, 'set') === 0) { $this->data[$key] = $args[0]; return $this; }
            return $this->data[$key] ?? null;
        }
    }
    class Quote extends Record {
        public $items = []; public $addresses = [];
        public function getItemById($id) { return $this->items[$id] ?? null; }
        public function getAddressById($id) { return $this->addresses[$id] ?? null; }
        public function getShippingAddressByCustomerAddressId($id) {
            foreach ($this->addresses as $address) if ($address->getCustomerAddressId() == $id) return $address;
            return null;
        }
        public function getAllVisibleItems() { return array_values($this->items); }
        public function getAllShippingAddresses() { return array_values($this->addresses); }
    }
    class Request {
        public $data = [];
        public function getPost($key, $default = null) { return $this->data[$key] ?? $default; }
        public function getParam($key, $default = null) { return $this->data[$key] ?? $default; }
        public function setParam($key, $value) { $this->data[$key] = $value; return $this; }
        public function setPostValue($key, $value) { return $this->setParam($key, $value); }
        public function isPost() { return true; }
    }
    require __DIR__.'/../core/Helper/Data.php';
    require __DIR__.'/../core/Model/CheckoutRouting.php';
    require __DIR__.'/../core/Block/RoutingState.php';
    require __DIR__.'/../core/Model/RecipientName.php';
    require __DIR__.'/../checkout-multishipping/Model/Recipient.php';
    require __DIR__.'/../checkout-multishipping/Block/Checkout/Addresses.php';
    require __DIR__.'/../checkout-multishipping/ViewModel/ShippingRouting.php';
    require __DIR__.'/../checkout-multishipping/Model/DealerGrouping.php';
    require __DIR__.'/../checkout-multishipping/Plugin/Multishipping/Controller/Checkout/AddressesPost.php';
    $quote = new Quote(['storeId'=>7, 'customerId'=>9, 'fflDealerData'=>json_encode(['addresses'=>[
        41=>['license'=>'gun-license', 'routingState'=>'TX'],
        42=>['license'=>'ammo-license', 'routingState'=>'CA']
    ]])]);
    foreach ([1=>'Firearm', 2=>'Ammo', 3=>'Regular'] as $id=>$name) $quote->items[$id] = new Record([
        'id'=>$id, 'name'=>$name, 'product'=>new Record(['isVirtual'=>false])
    ]);
    $quote->addresses = [101=>new Record(['customerAddressId'=>41,'regionCode'=>'TX']),
        102=>new Record(['customerAddressId'=>42,'regionCode'=>'TX']),
        103=>new Record(['customerAddressId'=>43,'regionCode'=>'CA'])];
    $helper = inject(\RefactoredGroup\AutoFflCore\Helper\Data::class, ['quote'=>$quote,
        'checkoutSession'=>new Record(['quote'=>$quote]), 'isEnabled'=>[7=>true],
        'quoteAnalysis'=>new class($quote) {
            private $quote;
            public function __construct($q) { $this->quote=$q; }
            public function analyze($quote, $state=null) {
                return ['required'=>$state==='CA'?[$quote->items[1],$quote->items[2]]:[$quote->items[1]],
                    'unresolved'=>$state==='', 'ammo'=>[['items'=>[$quote->items[2]],'states'=>['CA']]]];
            }
        }]);
    $block = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout\Addresses::class, ['autoFflHelper'=>$helper]);
    $block->checkout = new Record(['quote'=>$quote]);
    // Native Grid::filter creates fresh address items containing data but no protected address reference.
    $filteredAmmo = new Record(['quoteItemId'=>2, 'quoteAddressId'=>103, 'customerAddressId'=>43]);
    check($block->isFflItem($filteredAmmo), 'A filtered ammo row assigned to CA must offer dealer selection.');
    check(!$block->requiresRoutingState($filteredAmmo), 'The persisted CA destination must not appear unresolved.');
    $filteredAmmo->setQuoteAddressId(102)->setCustomerAddressId(42);
    check($block->isFflItem($filteredAmmo), 'A TX dealer must retain the original CA ammo routing state.');
    $filteredGun = new Record(['quoteItemId'=>1,'quoteAddressId'=>101]);
    check($block->isFflItem($filteredGun), 'Firearms must remain dealer-required.');
    $filteredRegular = new Record(['quoteItemId'=>3,'quoteAddressId'=>103]);
    check(!$block->isFflItem($filteredRegular), 'Ordinary products must retain native address selection.');
    $shippingRouting = new \RefactoredGroup\AutoFflCheckoutMultiShipping\ViewModel\ShippingRouting($helper);
    check($shippingRouting instanceof \Magento\Framework\View\Element\Block\ArgumentInterface,
        'Magento layout object arguments must implement the native block argument contract.');
    check($shippingRouting->hasFflItem($quote, [$filteredAmmo]) &&
        !$shippingRouting->hasFflItem($quote, [$filteredRegular]), 'Shipping methods must distinguish dealer and home addresses.');
    $config = json_decode($block->getSelectDealerConfig($filteredAmmo, 1), true);
    check($config['collectRecipientName']===false && $config['recipient_address_id']===42,
        'Multi-address checkout must use its saved address source without recipient inputs.');
    check($config['selected_address_id']===42 && $config['routingState']==='CA', 'Saved dealer and original ammo state must seed the reloaded selector.');
    $customer = new class implements \Magento\Customer\Api\Data\AddressInterface {
        public function getRegion() { return new Record(['regionCode'=>'CA']); }
    };
    check($helper->getAddressState($customer)==='CA', 'Posted native customer addresses need their RegionInterface code.');
    $request = new Request(); $messages = new class {
        public $errors=[]; public function addErrorMessage($error) { $this->errors[]=$error; }
    };
    $repo = new class($quote) {
        private $quote;
        public function __construct($q) { $this->quote=$q; }
        public function getById($id) {
            return new Record(['customerId'=>9, 'regionCode'=>$id==43?'CA':'TX']);
        }
    };
    $context = new Record(['request'=>$request,'messageManager'=>$messages]);
    $plugin = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout\AddressesPost::class,
        ['helper'=>$helper,'context'=>$context,'addresses'=>$repo]);
    $checkRouting = new \ReflectionMethod($plugin, 'checkDeliveryRouting');
    $request->data=['continue'=>1,'ship'=>[[1=>['qty'=>2,'address'=>41]],[2=>['qty'=>2,'address'=>43]],[3=>['qty'=>1,'address'=>43]]]];
    $checkRouting->invoke($plugin);
    check($request->getParam('continue')===0 && strpos($messages->errors[0],'Ammo')!==false,
        'Newly restricted ammo must persist its destination and return to dealer selection before shipping.');
    $request->data['continue']=1; $request->data['ship'][1][2]['address']=42;
    $checkRouting->invoke($plugin);
    check($request->getParam('continue')===1, 'Firearm and CA ammo with dealers plus ordinary home delivery must continue.');
    $request->data['continue']=1; $request->data['ship'][1][2]['address']=44;
    $checkRouting->invoke($plugin);
    check($request->getParam('continue')===1, 'Unrestricted TX ammunition must retain normal home shipping.');
    $request->data['ship'][0][1]['address']=0;
    try { $checkRouting->invoke($plugin); throw new \RuntimeException('Missing firearm address accepted.'); }
    catch (\Magento\Framework\Exception\LocalizedException $error) {
        check(strpos($error->getMessage(),'every item')!==false,'Missing addresses must block before native deletion.');
    }
    $quote->addresses[104] = new Record(['customerAddressId'=>44,'regionCode'=>'TX']);
    $quote->items[1]->setCustomerAddressId(41);
    $quote->items[2]->setCustomerAddressId(43);
    $quote->items[3]->setCustomerAddressId(43);
    $rows = [[1=>['qty'=>2,'address'=>41]],[2=>['qty'=>2,'address'=>44]],[3=>['qty'=>1,'address'=>43]]];
    $grouped = \RefactoredGroup\AutoFflCheckoutMultiShipping\Model\DealerGrouping::normalize(
        $quote, $rows, [1=>[2=>43]], $helper, $repo);
    check($grouped[0][1]['address']===41 && $grouped[1][2]['address']===41 && $grouped[2][3]['address']===43,
        'Selecting CA after the firearm dealer must join ammo to that dealer and preserve ordinary delivery.');
    $saved = json_decode($quote->getFflDealerData(),true)['addresses'][41];
    check($saved['routingStates'][2]==='CA' && $saved['routingAddressIds'][2]===43,
        'Grouping must preserve each ammo destination instead of using the firearm or dealer state.');
    check($helper->multishippingRoutingState($quote,$quote->addresses[101],$quote->items[2])==='CA',
        'Dealer-group ammo must still classify against CA even when the firearm routes through TX.');
    $ungrouped = \RefactoredGroup\AutoFflCheckoutMultiShipping\Model\DealerGrouping::normalize(
        $quote, $grouped, [1=>[2=>44]], $helper, $repo);
    check($ungrouped[1][2]['address']===44 && $ungrouped[0][1]['address']===41,
        'Switching ammo to TX must restore native delivery while the firearm keeps its dealer.');
    $quote->items[1]->setCustomerAddressId(44);
    $pending = \RefactoredGroup\AutoFflCheckoutMultiShipping\Model\DealerGrouping::normalize(
        $quote, [[1=>['qty'=>2,'address'=>0]],[2=>['qty'=>2,'address'=>44]],[3=>['qty'=>1,'address'=>43]]],
        [1=>[2=>43]], $helper, $repo);
    check($pending[0][1]['address']===44 && $pending[1][2]['address']===43,
        'Selecting CA before a firearm dealer must preserve all pending items for one shared selection.');
    $pending[0][1]['address']=41; $pending[1][2]['address']=41;
    $selected = \RefactoredGroup\AutoFflCheckoutMultiShipping\Model\DealerGrouping::normalize(
        $quote,$pending,[1=>[2=>43]],$helper,$repo);
    check($selected[0][1]['address']===41 && $selected[1][2]['address']===41,
        'Selecting the dealer after the CA destination must group both products identically.');
    check($selected[0][1]['qty']===2 && $selected[1][2]['qty']===2 && $selected[2][3]['qty']===1,
        'Grouping must preserve all quantities.');
    $unitRows = [];
    // The native cart has a regular product between firearm and ammo.
    $quote->items = [1=>$quote->items[1], 3=>$quote->items[3], 2=>$quote->items[2]];
    foreach ([3,2,2,1,1] as $id) {
        $unitRows[] = new Record(['quoteItemId'=>$id, 'quoteItem'=>$quote->items[$id], 'qty'=>1,
            'customerAddressId'=>$id===2?43:44]);
    }
    $grid = new class {
        public function create() { return $this; }
        public function addFilter($filter,$field) { return $this; }
        public function filter($items) { return $items; }
    };
    $r = new \ReflectionProperty($block,'_filterGridFactory'); $r->setValue($block,$grid);
    $block->checkout->setQuoteShippingAddressesItems($unitRows);
    $rows = $block->getItems();
    check(count($rows)===3 && array_map(fn($item)=>$item->getQuoteItemId(),$rows)===[1,2,3],
        'Native per-unit rows must collapse and always put ammunition directly after firearms.');
    check(array_map(fn($item)=>$item->getQty(),$rows)===[2,2,1] && $unitRows[0]->getQty()===1,
        'Collapsed quantities must be summed without changing native quote address items.');
    foreach ($unitRows as $row) {
        if ($row->getQuoteItemId()===2) $row->setCustomerAddressId(44);
    }
    $homeRows = $block->getItems();
    check(array_map(fn($item)=>$item->getQuoteItemId(),$homeRows)===[1,2,3] && !$block->isFflItem($homeRows[1]),
        'Home-delivery ammo must keep its row beneath firearms instead of changing position with its destination.');

    $quote->setId(933)->setIsActive(true)->setIsMultiShipping(true);
    $result = new class {
        public $data; public $code=200;
        public function setData($data) { $this->data=$data; return $this; }
        public function setHttpResponseCode($code) { $this->code=$code; return $this; }
    };
    $factory = new class($result) {
        private $result; public function __construct($result) { $this->result=$result; }
        public function create($type) { return $this->result; }
    };
    $native = new class {
        public $saved=[];
        public function getCustomerSession() { return new Record(['customerId'=>9]); }
        public function setShippingItemsInformation($ship) { $this->saved[]=$ship; }
    };
    $validator = new class {
        public $valid=true; public function validate($request) { return $this->valid; }
    };
    $async = inject(\RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout\AddressesPost::class,
        ['helper'=>$helper,'context'=>new Record(['request'=>$request,'resultFactory'=>$factory]),
            'addresses'=>$repo,'multishipping'=>$native,'formKeyValidator'=>$validator]);
    $request->data=['ffl_async_routing'=>1,'ship'=>$selected,'ffl_destination'=>[1=>[2=>43]]];
    $async->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(),
        function () { throw new \RuntimeException('Async save must not redirect or execute native continuation.'); });
    check($result->data['success'] && count($native->saved)===1 && $native->saved[0][1][2]['address']===41,
        'Background saves must normalize grouping and use native address persistence without navigation.');
    $validator->valid=false;
    $async->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(),fn()=>null);
    check(!$result->data['success'] && $result->code===400 && count($native->saved)===1,
        'AJAX routing must explicitly reject an invalid form key before saving.');
    $validator->valid=true; $quote->setIsActive(false);
    $async->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(),fn()=>null);
    check(!$result->data['success'] && count($native->saved)===1,'An inactive cart must not accept background routing.');
    $quote->setIsActive(true);
    $foreign = new class {
        public function getById($id) { return new Record(['customerId'=>10,'regionCode'=>'CA']); }
    };
    (new \ReflectionProperty($async,'addresses'))->setValue($async,$foreign);
    $async->aroundExecute(new \Magento\Multishipping\Controller\Checkout\AddressesPost(),fn()=>null);
    check(!$result->data['success'] && count($native->saved)===1,
        'Background routing must reject another customer\'s address before native cart mutation.');
    // Mixed carts choose the ammo state before checkout. The selected state
    // remains available to routing without asking for it a second time.
    $destinationQuote = new Quote(['id'=>1, 'storeId'=>7, 'fflRoutingState'=>'UT']);
    $destinationQuote->items = [
        1=>new Record(['id'=>1, 'productId'=>11, 'qty'=>1]),
        2=>new Record(['id'=>2, 'productId'=>12, 'qty'=>1])
    ];
    $destinationSession = new Record();
    $destinationAnalysis = new class {
        public $snapshot = ['hasAmmunition'=>true, 'physicalCount'=>2,
            'ammo'=>[['items'=>['ammo'], 'states'=>['UT']]], 'firearms'=>['firearm'],
            'ordinary'=>[], 'required'=>['firearm', 'ammo'], 'allRequired'=>true, 'unresolved'=>false];
        public function analyze($quote, $state=null) { return $this->snapshot; }
    };
    $destinationHelper = inject(\RefactoredGroup\AutoFflCore\Helper\Data::class,
        ['quote'=>$destinationQuote, 'checkoutSession'=>$destinationSession,
            'isEnabled'=>[7=>true], 'quoteAnalysis'=>$destinationAnalysis,
            'formKey'=>new Record(['formKey'=>'fixture'])]);
    $destinationBlock = inject(\RefactoredGroup\AutoFflCore\Block\RoutingState::class,
        ['helper'=>$destinationHelper]);
    check(!$destinationHelper->showRoutingStateInCheckout(),
        'Firearms plus ammo must not ask for the pre-checkout state again in the shipping form.');
    check($destinationHelper->getCheckoutRoutingConfig()['selectedState']==='UT' &&
        $destinationHelper->getCheckoutRoute()==='standard' && !$destinationBlock->shouldRender(),
        'A saved mixed-cart destination must remain available when entering dealer checkout.');
    check($destinationHelper->getCheckoutEntryRoute()==='state',
        'Adding a firearm after dealer-required ammo checkout must reopen the destination screen.');
    $destinationBlock->nameInLayout='autoffl.destination';
    check($destinationBlock->shouldRender(),
        'The dedicated state screen must render for a saved whole-cart dealer destination.');
    $destinationBlock->nameInLayout='autoffl.cart.destination';
    check(!$destinationHelper->consumeCheckoutEntry(), 'A saved state alone must not confirm mixed-cart checkout.');
    $destinationHelper->allowCheckoutEntry();
    check($destinationHelper->consumeCheckoutEntry() && !$destinationHelper->consumeCheckoutEntry(),
        'Confirming the destination must allow exactly one native checkout entry without a redirect loop.');
    $destinationHelper->allowCheckoutEntry();
    $destinationQuote->items[2]->setQty(2);
    check(!$destinationHelper->consumeCheckoutEntry(), 'Changing cart quantities must invalidate the old confirmation.');
    $destinationHelper->allowCheckoutEntry();
    $destinationQuote->items[3]=new Record(['id'=>3, 'productId'=>13, 'qty'=>1]);
    check(!$destinationHelper->consumeCheckoutEntry(), 'Adding another product must invalidate the old confirmation.');
    unset($destinationQuote->items[3]);
    $destinationHelper->allowCheckoutEntry();
    $destinationQuote->setFflRoutingState('CA');
    check(!$destinationHelper->consumeCheckoutEntry(), 'Changing the ammunition state must invalidate the old confirmation.');
    $destinationQuote->setFflRoutingState('UT');
    $destinationHelper->allowCheckoutEntry();
    $destinationQuote->setId(2);
    check(!$destinationHelper->consumeCheckoutEntry(), 'A confirmation must not carry into another quote.');
    $destinationHelper->allowCheckoutEntry();
    $destinationHelper->allowCheckoutEntry(false);
    check(!$destinationHelper->consumeCheckoutEntry(), 'Separate-address routing must revoke a previous native checkout confirmation.');
    $destinationQuote->setFflRoutingState('CO');
    $destinationAnalysis->snapshot['required']=['firearm'];
    $destinationAnalysis->snapshot['allRequired']=false;
    check($destinationHelper->getCheckoutRoute()==='multishipping' &&
        $destinationHelper->getCheckoutEntryRoute()==='state',
        'A saved unrestricted ammo state must not bypass the mixed-cart destination screen.');
    check(!$destinationBlock->shouldRender(),
        'A saved destination must not add another state form to the cart.');
    $destinationBlock->nameInLayout='autoffl.destination';
    check($destinationBlock->shouldRender(),
        'The dedicated destination screen must render even when a previous ammo state needs separate shipping.');
    $destinationBlock->nameInLayout='autoffl.cart.destination';
    $destinationQuote->setFflRoutingState('');
    $destinationAnalysis->snapshot['unresolved']=true;
    $destinationAnalysis->snapshot['required']=['firearm'];
    $destinationAnalysis->snapshot['allRequired']=false;
    check($destinationBlock->shouldRender(), 'Mixed carts must retain the pre-checkout state screen.');
    $destinationAnalysis->snapshot['ordinary']=['ordinary'];
    $destinationAnalysis->snapshot['physicalCount']=3;
    foreach (['', 'UT', 'CO'] as $state) {
        $destinationQuote->setFflRoutingState($state);
        $destinationAnalysis->snapshot['unresolved']=$state==='';
        $destinationAnalysis->snapshot['required']=$state==='UT'?['firearm','ammo']:['firearm'];
        check($destinationHelper->getCheckoutRoute()==='multishipping' &&
            $destinationHelper->getCheckoutEntryRoute()==='multishipping',
            'Firearms and ordinary products must skip the ammo prompt because separate addresses are already required.');
        $destinationBlock->nameInLayout='autoffl.destination';
        check(!$destinationBlock->shouldRender(),
            'An inevitable address split must not show the dedicated ammunition destination form.');
        $destinationBlock->nameInLayout='autoffl.cart.destination';
    }
    $destinationAnalysis->snapshot['required']=['firearm','ammo','ordinary'];
    $destinationAnalysis->snapshot['allRequired']=true;
    check($destinationHelper->getCheckoutRoute()==='standard' &&
        $destinationHelper->getCheckoutEntryRoute()==='state',
        'A store shipping all products to a dealer must retain its existing single-address routing.');
    $destinationAnalysis->snapshot['allRequired']=false;
    $destinationAnalysis->snapshot['ordinary']=[];
    $destinationQuote->setFflRoutingState('');
    $destinationAnalysis->snapshot['unresolved']=true;
    $destinationAnalysis->snapshot['physicalCount']=1;
    $destinationAnalysis->snapshot['firearms']=[];
    $destinationAnalysis->snapshot['required']=[];
    check($destinationHelper->showRoutingStateInCheckout() && !$destinationBlock->shouldRender(),
        'Ammo-only carts must still collect the destination directly in checkout.');
    check($destinationHelper->getCheckoutEntryRoute()==='standard',
        'Ammo-only checkout must retain its inline destination field.');
    $destinationAnalysis->snapshot['ammo']=[];
    $destinationAnalysis->snapshot['hasAmmunition']=false;
    $destinationAnalysis->snapshot['unresolved']=false;
    $destinationAnalysis->snapshot['firearms']=['firearm'];
    $destinationAnalysis->snapshot['required']=['firearm'];
    $destinationAnalysis->snapshot['allRequired']=true;
    check(!$destinationHelper->showRoutingStateInCheckout(), 'Firearms-only carts need no ammo state field.');
    $destinationAnalysis->snapshot['ordinary']=['ordinary'];
    $destinationAnalysis->snapshot['physicalCount']=2;
    $destinationAnalysis->snapshot['allRequired']=false;
    check($destinationHelper->getCheckoutEntryRoute()==='multishipping',
        'Firearms and ordinary products without conditional ammo must keep their existing checkout route.');
    $destinationAnalysis->snapshot['ammo']=[['items'=>['ammo'], 'states'=>['UT']]];
    $destinationAnalysis->snapshot['hasAmmunition']=true;
    (new \ReflectionProperty($destinationHelper, 'isEnabled'))->setValue($destinationHelper, [7=>false]);
    check(!$destinationHelper->showRoutingStateInCheckout(), 'Disabled routing must not add a state field.');
    echo "Filtered multishipping rows, saved dealers, mixed CA/TX ammo routing, destination visibility, and continuation guards passed.\n";
}
