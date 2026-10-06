<?php
namespace RefactoredGroup\AutoFflCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Psr\Log\LoggerInterface;
use RefactoredGroup\AutoFflCore\Model\AsyncOrderConfig;

class ScheduleAttribution implements ObserverInterface
{
    private $orders;
    private $multishipping;
    private $asyncOrder;
    private $logger;

    public function __construct(
        OrderResource $orders,
        Multishipping $multishipping,
        AsyncOrderConfig $asyncOrder,
        LoggerInterface $logger
    ) {
        $this->orders = $orders;
        $this->multishipping = $multishipping;
        $this->asyncOrder = $asyncOrder;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        // checkout_submit_all_after runs after the order has been committed.
        // Optional attribution must never make a successful order look failed.
        try {
            $this->scheduleOrders($observer);
        } catch (\Throwable $e) {
            $this->logger->error('Could not schedule AutoFFL attribution', [
                'reason' => 'local_observer_failed'
            ]);
        }
    }

    private function scheduleOrders(Observer $observer)
    {
        if ($this->asyncOrder->isEnabled()) {
            $this->logger->warning('AutoFFL Magento attribution is unsupported with AsyncOrder enabled');
            return;
        }
        $single = $observer->getData('order');
        if ($single instanceof Order) {
            $this->schedule($single);
            return;
        }
        $attempted = $observer->getData('orders');
        if (!is_array($attempted)) {
            return;
        }
        $confirmed = array_map('strval', (array) $this->multishipping->getOrderIds());
        foreach ($attempted as $order) {
            if ($order instanceof Order &&
                (in_array((string) $order->getId(), $confirmed, true) ||
                    in_array((string) $order->getIncrementId(), $confirmed, true))) {
                $this->schedule($order);
            }
        }
    }

    private function schedule(Order $order)
    {
        $snapshot = json_decode((string) $order->getFflDealerData(), true);
        if (!$order->getId() || !is_array($snapshot) || empty($snapshot['id']) || empty($snapshot['license'])) {
            return;
        }
        try {
            $connection = $this->orders->getConnection();
            $connection->update(
                $this->orders->getMainTable(),
                [
                    'ffl_attribution_state' => 'pending',
                    'ffl_attribution_attempts' => 0,
                    'ffl_attribution_next_at' => gmdate('Y-m-d H:i:s')
                ],
                $connection->quoteInto('entity_id = ? AND ffl_attribution_state IS NULL', (int) $order->getId())
            );
        } catch (\Throwable $e) {
            $this->logger->error('Could not schedule AutoFFL attribution', [
                'order_id' => (int) $order->getId(),
                'reason' => 'local_state_transition_failed'
            ]);
        }
    }
}
