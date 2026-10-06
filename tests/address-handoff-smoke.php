<?php
namespace Magento\Framework\Exception { class LocalizedException extends \Exception {} }
namespace Magento\Checkout\Model {
    class Session {
        private $data = [];
        public function setData($key, $value) { $this->data[$key] = $value; }
        public function getData($key) { return $this->data[$key] ?? null; }
        public function unsetData($key) { unset($this->data[$key]); }
    }
}
namespace Magento\Customer\Api { interface AddressRepositoryInterface {} }
namespace Magento\Customer\Api\Data {
    class AddressInterfaceFactory { public function create() { return new \FixtureAddress(); } }
}
namespace {
    require __DIR__ . '/../core/Model/AddressHandoff.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    class FixtureAddress {
        public $data = [];
        public function __call($method, $arguments) {
            $key = substr($method, 3);
            if (strpos($method, 'set') === 0) { $this->data[$key] = $arguments[0]; return $this; }
            return $this->data[$key] ?? null;
        }
    }
    class FixtureRepository implements \Magento\Customer\Api\AddressRepositoryInterface {
        public $saved = []; public $existing; public $fail = false;
        public function save($address) {
            if ($this->fail) throw new \Magento\Framework\Exception\LocalizedException('Custom field required');
            $address->setId(101); $this->saved[] = $address; return $address;
        }
        public function getById($id) { return $this->existing; }
    }
    class FixtureQuote { public $store = 7; public function getStoreId() { return $this->store; } }
    class FixtureCustomer { public $id = 0; public function getId() { return $this->id; } }
    $session = new \Magento\Checkout\Model\Session(); $repository = new FixtureRepository();
    $handoff = new \RefactoredGroup\AutoFflCore\Model\AddressHandoff($session, $repository,
        new \Magento\Customer\Api\Data\AddressInterfaceFactory());
    $quote = new FixtureQuote(); $customer = new FixtureCustomer();
    $home = ['firstname' => 'Jane', 'lastname' => 'Buyer', 'street' => ['10 Home St'], 'city' => 'Home City',
        'postcode' => '90210', 'telephone' => '5551231234', 'ffl_license' => 'must-not-copy', 'customerId' => 99];
    $handoff->capture($quote, $home, 12);
    check($handoff->restoreForCustomer($quote, $customer) === null && !$repository->saved,
        'A guest address must not be saved to an account before authentication.');
    $customer->id = 42;
    check($handoff->restoreForCustomer($quote, $customer) === 101, 'A guest address must survive login.');
    check($repository->saved[0]->getCustomerId() === 42, 'The authenticated owner must be server supplied.');
    check(!isset($repository->saved[0]->data['FflLicense']), 'Dealer metadata must never be copied.');
    check(!isset($repository->saved[0]->data['IsDefaultShipping']), 'Account defaults must not be changed.');
    check(!$handoff->getPending($quote), 'Restored addresses must be consumed once.');
    $repository->existing = new FixtureAddress(); $repository->existing->setId(5)->setCustomerId(99);
    $handoff->capture($quote, $home + ['customerAddressId' => 5], 12);
    check($handoff->restoreForCustomer($quote, $customer) === 101,
        'An address belonging to someone else must not be reused.');
    check(count($repository->saved) === 2 && $repository->saved[1]->getCustomerId() === 42,
        'The entered fields can instead create an address owned by the signed-in customer.');
    $repository->existing = $repository->saved[1];
    $handoff->capture($quote, $home + ['customerAddressId' => 101], 12);
    check($handoff->restoreForCustomer($quote, $customer) === 101 && count($repository->saved) === 2,
        'An identical address owned by the customer should be reused without a duplicate.');
    $changed = $home; $changed['street'] = ['20 New Home St']; $changed['customerAddressId'] = 101;
    $handoff->capture($quote, $changed, 12);
    $handoff->restoreForCustomer($quote, $customer);
    check(count($repository->saved) === 3 && $repository->saved[2]->getStreet() === ['20 New Home St'],
        'Edited address fields must be carried over without changing the original address book record.');
    $handoff->capture($quote, $home, 12); $quote->store = 8;
    check(!$handoff->getPending($quote), 'Address handoff must not cross stores.');
    $quote->store = 7; $repository->fail = true; $handoff->capture($quote, $home, 12);
    check($handoff->restoreForCustomer($quote, $customer) === null && $handoff->getPending($quote),
        'Required custom address fields must preserve the data for native form completion.');
    $handoff->clear(); $handoff->capture($quote, ['firstname' => 'Partial'], 12);
    check(!$handoff->getPending($quote), 'Incomplete address data must not be saved to an account.');
    echo "Address handoff smoke checks passed.\n";
}
