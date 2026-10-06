<?php
// Optional attribution must not fail checkout after an order has committed.
namespace Magento\Framework\Event {
    interface ObserverInterface {}
    class Observer {
        private $data;
        public function __construct(array $data) { $this->data = $data; }
        public function getData($name) { return $this->data[$name] ?? null; }
    }
}
namespace Magento\Framework\Module {
    class Manager {
        public $async = false;
        public function isEnabled($name) { return $this->async; }
    }
}
namespace Magento\Framework\App {
    class DeploymentConfig {
        public $async = 0;
        public function get($path, $default = null) { return $path === 'checkout/async' ? $this->async : $default; }
    }
}
namespace Magento\Sales\Model {
    class Order {
        public $id; public $increment; public $snapshot;
        public function __construct($id, $increment, $snapshot) {
            $this->id = $id; $this->increment = $increment; $this->snapshot = $snapshot;
        }
        public function getId() { return $this->id; }
        public function getIncrementId() { return $this->increment; }
        public function getFflDealerData() { return $this->snapshot; }
    }
}
namespace Magento\Sales\Model\ResourceModel {
    class Order {
        public $updates = []; public $fail = false;
        public function getConnection() { return $this; }
        public function getMainTable() { return 'sales_order'; }
        public function quoteInto($condition, $id) { return str_replace('?', (string) $id, $condition); }
        public function update($table, $data, $condition) {
            if ($this->fail) throw new \Error('Local attribution state update failed.');
            $this->updates[] = ['table' => $table, 'data' => $data, 'condition' => $condition];
        }
    }
}
namespace Magento\Multishipping\Model\Checkout\Type {
    class Multishipping {
        public $calls = 0; public $fail = false;
        public function getOrderIds() {
            $this->calls++;
            if ($this->fail) throw new \Error('Storefront-only dependency unavailable in REST.');
            return [102];
        }
    }
}
namespace Psr\Log {
    interface LoggerInterface {}
}
namespace {
    require __DIR__ . '/../core/Model/AsyncOrderConfig.php';
    require __DIR__ . '/../core/Observer/ScheduleAttribution.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    class Logger implements \Psr\Log\LoggerInterface {
        public $errors = []; public $warnings = [];
        public function error($message, $context) { $this->errors[] = $context; }
        public function warning($message) { $this->warnings[] = $message; }
    }
    $resource = new \Magento\Sales\Model\ResourceModel\Order();
    $multishipping = new \Magento\Multishipping\Model\Checkout\Type\Multishipping();
    $modules = new \Magento\Framework\Module\Manager(); $logger = new Logger();
    $deployment = new \Magento\Framework\App\DeploymentConfig();
    $asyncOrder = new \RefactoredGroup\AutoFflCore\Model\AsyncOrderConfig($modules, $deployment);
    $observer = new \RefactoredGroup\AutoFflCore\Observer\ScheduleAttribution($resource, $multishipping, $asyncOrder, $logger);
    $snapshot = json_encode(['id' => 921, 'license' => 'fixture-license']);
    $single = new \Magento\Sales\Model\Order(101, '000000101', $snapshot);
    $observer->execute(new \Magento\Framework\Event\Observer(['order' => $single]));
    check(count($resource->updates) === 1 && $resource->updates[0]['data']['ffl_attribution_state'] === 'pending',
        'A committed dealer order must schedule local attribution.');
    check($multishipping->calls === 0, 'Standard checkout must not load the storefront multishipping model.');
    check(strpos($resource->updates[0]['condition'], 'ffl_attribution_state IS NULL') !== false,
        'Repeated events must not reset an existing attribution attempt.');

    $resource->fail = true;
    $observer->execute(new \Magento\Framework\Event\Observer(['order' => $single]));
    check(end($logger->errors)['reason'] === 'local_state_transition_failed',
        'A local attribution Error must be logged without failing the completed order response.');
    $resource->fail = false;
    $confirmed = new \Magento\Sales\Model\Order(102, '000000102', $snapshot);
    $failed = new \Magento\Sales\Model\Order(103, '000000103', $snapshot);
    $observer->execute(new \Magento\Framework\Event\Observer(['orders' => [$confirmed, $failed]]));
    check(count($resource->updates) === 2 && strpos($resource->updates[1]['condition'], '102') !== false,
        'Only confirmed child orders may schedule attribution after partial multishipping placement.');

    $multishipping->fail = true;
    $observer->execute(new \Magento\Framework\Event\Observer(['orders' => [$confirmed]]));
    check(end($logger->errors)['reason'] === 'local_observer_failed',
        'An optional observer dependency Error must not escape after order commit.');
    $modules->async = true;
    $observer->execute(new \Magento\Framework\Event\Observer(['order' => $single]));
    check(count($resource->updates) === 3 && !$logger->warnings,
        'Synchronous Commerce orders must report even when the AsyncOrder module is installed and enabled.');
    $deployment->async = 1;
    $observer->execute(new \Magento\Framework\Event\Observer(['order' => $single]));
    check(count($resource->updates) === 3 && count($logger->warnings) === 1,
        'Unsupported async orders must remain unscheduled.');
    echo "Post-order attribution isolation smoke checks passed.\n";
}
