<?php
namespace Magento\Framework\Exception { class LocalizedException extends \Exception {} }
namespace Magento\Framework\App { interface CsrfAwareActionInterface {} interface RequestInterface {} }
namespace Magento\Framework\App\Action { class Action {
    public $response;
    public function getResponse() { return $this->response ??= new \Record(); }
} }
namespace Magento\Framework\App\Request { class InvalidRequestException extends \Exception {} }
namespace {
    function __($text) { return $text; }
    function check($value, $message) { if (!$value) throw new \RuntimeException($message); }
    class Record {
        public $data;
        public function __construct(array $data = []) { $this->data = $data; }
        public function __call($method, $args) {
            $key = lcfirst(substr($method, 3));
            if (strpos($method,'set')===0) { $this->data[$key]=$args[0]; return $this; }
            return $this->data[$key] ?? null;
        }
        public function setCustomAttribute($key,$value) { $this->data[$key]=$value; return $this; }
    }
    class Quote extends Record {
        public $items=[];
        public function getItemById($id) { return $this->items[$id] ?? null; }
        public function getAllVisibleItems() { return array_values($this->items); }
    }
    class Request {
        public $data=[];
        public function isPost() { return true; }
        public function getParams() { return $this->data; }
    }
    class Addresses {
        public $stored=[]; public $saves=0;
        public function getById($id) { return $this->stored[$id]; }
        public function save($address) {
            $this->saves++; $id=49+$this->saves;
            $address->setId($id); $this->stored[$id]=clone $address; return $address;
        }
    }
    require __DIR__.'/../core/Model/RecipientName.php';
    require __DIR__.'/../checkout-multishipping/Model/DealerGrouping.php';
    require __DIR__.'/../checkout-multishipping/Controller/Index/Index.php';
    $quote=new Quote(['id'=>933,'customerId'=>9,'isActive'=>true,'isMultiShipping'=>true,'storeId'=>7]);
    foreach ([1,2,3] as $id) $quote->items[$id]=new Record(['id'=>$id,'product'=>new Record(['isVirtual'=>false])]);
    $addresses=new Addresses();
    $addresses->stored=[10=>new Record(['customerId'=>9,'firstname'=>'Saved','lastname'=>'Recipient','state'=>'TX']),
        11=>new Record(['customerId'=>9,'firstname'=>'Ammo','lastname'=>'Person','state'=>'CA']),
        12=>new Record(['customerId'=>9,'firstname'=>'Home','lastname'=>'Person','state'=>'MI'])];
    $helper=new class {
        public $enabled = true;
        public function isEnabled($store) { return $this->enabled; }
        public function hasFflItem($quote) {
            foreach ($quote->items as $item) {
                if ($this->isFflItem($item, $quote, $quote->getFflRoutingState())) return true;
            }
            return false;
        }
        public function isConditionalAmmoItem($item,$quote) { return $item->getId()===2; }
        public function isFflItem($item,$quote,$state) { return $item->getId()===1 || ($item->getId()===2 && $state==='CA'); }
        public function isUnresolvedAmmoItem($item,$quote,$state) { return $item->getId()===2 && !$state; }
        public function getAddressState($address) { return $address->getState(); }
        public function getMultishippingItemAddress($quote,$item) { return null; }
    };
    $native=new class($quote) {
        public $quote; public $calls=0; public $rows; public $fail=false;
        public function __construct($quote) { $this->quote=$quote; }
        public function getQuote() { return $this->quote; }
        public function setShippingItemsInformation($rows) {
            $this->calls++; $this->rows=$rows;
            if ($this->fail) throw new \RuntimeException('Persistence failed');
        }
    };
    $connection=new class($addresses) {
        public $begins=0; public $commits=0; public $rollbacks=0; private $addresses; private $before;
        public function __construct($addresses) { $this->addresses=$addresses; }
        public function getConnection() { return $this; }
        public function beginTransaction() { $this->begins++; $this->before=$this->addresses->stored; }
        public function commit() { $this->commits++; }
        public function rollBack() { $this->rollbacks++; $this->addresses->stored=$this->before; }
    };
    $quotes=new class { public $saves=0; public function save($quote) { $this->saves++; } };
    $request=new Request();
    $account=new Record(['firstname'=>'Account','lastname'=>'Customer']);
    $snapshot=['id'=>921,'license'=>'fixture','company'=>'Dealer Business','state'=>'TX','countryCode'=>'US',
        'address1'=>'10 Main','address2'=>'','city'=>'Dallas','postalCode'=>'75056','phone'=>'5551231234','routingState'=>'TX'];
    $fields=[
        '_rawFactory'=>new class { public function create() { return new Record(); } }, 'request'=>$request,
        'customerSession'=>new Record(['customerId'=>9,'customer'=>$account]),
        'checkoutSession'=>new Record(['quote'=>$quote]),'helper'=>$helper,'addressRepository'=>$addresses,
        'addressDataFactory'=>new class { public function create() { return new Record(); } },
        'quoteRepository'=>$quotes,'multishipping'=>$native,'resourceConnection'=>$connection,
        'logger'=>new class { public function error($message,$context) {} },
        'snapshot'=>new class($snapshot) { private $value; public function __construct($v) { $this->value=$v; }
            public function fromJson($json,$license,$store) { return $this->value; } },
        'regionCollectionFactory'=>new class { public function create() { return $this; }
            public function addFieldToFilter($f,$v) { return $this; } public function getFirstItem() { return $this; }
            public function getDataByKey($key) { return 57; } }
    ];
    $reflection=new \ReflectionClass(\RefactoredGroup\AutoFflCheckoutMultiShipping\Controller\Index\Index::class);
    $controller=$reflection->newInstanceWithoutConstructor();
    foreach ($fields as $key=>$value) $reflection->getProperty($key)->setValue($controller,$value);
    $request->data=['recipient_address_id'=>10,'ship'=>[[1=>['qty'=>3,'address'=>10]],
        [2=>['qty'=>3,'address'=>11]],[3=>['qty'=>2,'address'=>12]]],'ffl_destination'=>[1=>[2=>11]]];
    $result=json_decode($controller->execute()->getContents(),true);
    check($result['assignments_saved']===true,'Creation and assignment must complete in one response.');
    check($native->calls===1 && $quotes->saves===0,'Use one native persistence operation without an earlier quote save.');
    check($native->rows[0][1]['address']===50 && $native->rows[1][2]['address']===50 && $native->rows[2][3]['address']===12,
        'Firearms and restricted ammo share the new dealer; ordinary items retain their own address.');
    check(array_column(array_map('current',$native->rows),'qty')===[3,3,2],'Combined save must retain all quantities.');
    $saved=json_decode($quote->getFflDealerData(),true)['addresses'][50];
    check($saved['routingStates'][2]==='CA' && $saved['routingAddressIds'][2]===11,
        'Ammo must retain its original California destination despite its Texas dealer.');
    check($addresses->stored[50]->getFirstname()==='Saved' && $connection->commits===1,'Saved recipient and atomic commit must be retained.');

    // A firearms-only cart has no ordinary item supplying the recipient name.
    $quote->items=[1=>$quote->items[1]]; $quote->setFflDealerData(null);
    $request->data=['ship'=>[[1=>['qty'=>3,'address'=>10]]]];
    $result=json_decode($controller->execute()->getContents(),true);
    check($result['firstname']==='Account' && $native->rows[0][1]['qty']===3,'Account names must cover a firearms-only cart without a recipient source.');
    $request->data['recipient_override']=1;
    $request->data['recipient_first_name']='Chosen'; $request->data['recipient_last_name']='Buyer';
    $result=json_decode($controller->execute()->getContents(),true);
    check($result['firstname']==='Chosen' && $addresses->stored[10]->getFirstname()==='Saved',
        'Explicit recipient edits must never modify the saved source address.');

    $before=$quote->getFflDealerData(); $native->fail=true;
    $result=json_decode($controller->execute()->getContents(),true);
    check($result['success']===false && $connection->rollbacks===1 && $quote->getFflDealerData()===$before,
        'Failed native assignment must roll back dealer creation and metadata and return a usable error.');
    $native->fail=false; unset($request->data['recipient_override']);
    $account->setFirstname('')->setLastname('');
    $beforeSaves=$addresses->saves;
    $result=json_decode($controller->execute()->getContents(),true);
    check($result['success']===false && $addresses->saves===$beforeSaves,'Missing names must block before creating an address.');
    $account->setFirstname('Account')->setLastname('Customer');
    $quote->items[3]=new Record(['id'=>3,'product'=>new Record(['isVirtual'=>false])]);
    $result=json_decode($controller->execute()->getContents(),true);
    check($result['success']===false && $addresses->saves===$beforeSaves,'Incomplete assignment payloads must not delete unposted products.');
    $quote->items = [2=>new Record(['id'=>2,'product'=>new Record(['isVirtual'=>false])]), 3=>$quote->items[3]];
    $quote->setFflRoutingState('TX')->setFflDealerData(null);
    $request->data = ['ship'=>[[2=>['qty'=>2,'address'=>10]], [3=>['qty'=>1,'address'=>12]]],
        'ffl_destination'=>[0=>[2=>11]]];
    check(!$helper->hasFflItem($quote), 'The saved cart state is deliberately unrestricted.');
    $result = json_decode($controller->execute()->getContents(), true);
    check(($result['assignments_saved'] ?? false) && $native->rows[0][2]['address'] === $result['id'] &&
        $native->rows[1][3]['address'] === 12,
        'Restricted ammo uses its posted CA destination even when the cart previously selected TX.');
    // Reloaded selections preserve the original state even when the chosen dealer is in Texas.
    $previousDealerId = $result['id'];
    $request->data['ship'][0][2]['address'] = $previousDealerId;
    $request->data['replaces_address_id'] = $previousDealerId;
    unset($request->data['ffl_destination']);
    $result = json_decode($controller->execute()->getContents(), true);
    check(($result['assignments_saved'] ?? false), 'Saved per-item routing provenance must support dealer replacement.');
    $replaced = json_decode($quote->getFflDealerData(), true)['addresses'];
    check($result['id'] !== $previousDealerId && !isset($replaced[$previousDealerId]) &&
        $replaced[$result['id']]['routingStates'][2] === 'CA' &&
        $replaced[$result['id']]['routingAddressIds'][2] === 11 && $native->rows[0][2]['address'] === $result['id'],
        'Replacing a Texas dealer must transfer the original California destination to the new selection.');
    $quote->items = [2=>$quote->items[2]]; $quote->setFflDealerData(null);
    $request->data = ['ship'=>[[2=>['qty'=>1,'address'=>11]]]];
    $result = json_decode($controller->execute()->getContents(), true);
    check(($result['assignments_saved'] ?? false), 'An ammo-only cart can explicitly use multishipping with a restricted destination.');
    $beforeSaves = $addresses->saves;
    $quote->setFflDealerData(null)->setFflRoutingState('CA');
    $request->data = ['ship'=>[[2=>['qty'=>1,'address'=>10]]]];
    $result = json_decode($controller->execute()->getContents(), true);
    check(($result['success'] ?? null) === false && $addresses->saves === $beforeSaves,
        'An unrestricted per-item destination must not create a dealer even if the saved cart state is restricted.');
    $addresses->stored[13] = new Record(['customerId'=>99,'state'=>'CA']);
    $addresses->stored[14] = new Record(['customerId'=>9,'state'=>'']);
    foreach ([13, 14] as $destination) {
        $request->data['ffl_destination'] = [0=>[2=>$destination]];
        $result = json_decode($controller->execute()->getContents(), true);
        check(($result['success'] ?? null) === false && $addresses->saves === $beforeSaves,
            'An unowned or unresolved destination must be rejected before dealer creation.');
    }
    $quote->items = [3=>new Record(['id'=>3,'product'=>new Record(['isVirtual'=>false])])];
    $request->data = ['ship'=>[[3=>['qty'=>1,'address'=>11]]]];
    $result = json_decode($controller->execute()->getContents(), true);
    check(($result['success'] ?? null) === false && $addresses->saves === $beforeSaves,
        'An ordinary cart must not create a dealer selection.');
    $quote->items = [2=>new Record(['id'=>2,'product'=>new Record(['isVirtual'=>false])])];
    $request->data = ['ship'=>[[2=>['qty'=>1,'address'=>11]]]];
    $helper->enabled = false;
    $result = json_decode($controller->execute()->getContents(), true);
    check(($result['success'] ?? null) === false && $addresses->saves === $beforeSaves,
        'A disabled store cannot create a dealer selection.');
    echo "Combined dealer creation, recipient fallbacks, native grouping, and atomic failure checks passed.\n";
}
