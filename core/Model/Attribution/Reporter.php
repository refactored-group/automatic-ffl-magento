<?php
namespace RefactoredGroup\AutoFflCore\Model\Attribution;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class Reporter
{
    private const BATCH_SIZE = 25;
    private const MAX_ATTEMPTS = 5;
    private const DELAYS = [60, 300, 900, 3600];

    private $orders;
    private $orderResource;
    private $lock;
    private $curlFactory;
    private $helper;
    private $logger;
    private $retryAfter = 0;

    public function __construct(
        CollectionFactory $orders,
        OrderResource $orderResource,
        LockManagerInterface $lock,
        CurlFactory $curlFactory,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->orders = $orders;
        $this->orderResource = $orderResource;
        $this->lock = $lock;
        $this->curlFactory = $curlFactory;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->lock->lock('autoffl_order_attributions', 0)) {
            return;
        }
        try {
            $collection = $this->orders->create()
                ->addFieldToFilter('ffl_attribution_state', 'pending')
                ->addFieldToFilter('ffl_attribution_next_at', ['lteq' => gmdate('Y-m-d H:i:s')])
                ->setPageSize(self::BATCH_SIZE)
                ->setCurPage(1);
            foreach ($collection as $order) {
                $this->sendOrder($order);
            }
        } finally {
            $this->lock->unlock('autoffl_order_attributions');
        }
    }

    private function sendOrder($order)
    {
        $orderId = (int) $order->getId();
        $storeId = (int) $order->getStoreId();
        $snapshot = json_decode((string) $order->getFflDealerData(), true);
        if (!is_array($snapshot) || empty($snapshot['id']) || empty($snapshot['license']) ||
            !isset($snapshot['storeHash'], $snapshot['sandbox']) ||
            $snapshot['storeHash'] !== $this->helper->getStoreHash($storeId) ||
            (bool) $snapshot['sandbox'] !== $this->helper->isSandboxMode($storeId) ||
            !$this->helper->getStoreSecret($storeId)) {
            $this->transition($orderId, 'blocked', (int) $order->getFflAttributionAttempts(), null);
            $this->logReason($orderId, 'configuration_missing_or_changed');
            return;
        }
        $previousAttempts = (int) $order->getFflAttributionAttempts();
        if ($previousAttempts >= self::MAX_ATTEMPTS) {
            $this->transition($orderId, 'failed', $previousAttempts, null);
            return;
        }
        $attempts = $previousAttempts + 1;
        $this->retryAfter = 0;
        $this->transition($orderId, 'pending', $attempts, gmdate('Y-m-d H:i:s', time() + 3600));

        try {
            $response = $this->deliver($order, $snapshot);
            if ($response === 'recorded' || $response === 'duplicate') {
                $this->transition($orderId, 'sent', $attempts, null);
                return;
            }
            if ($response === 'auth') {
                $this->transition($orderId, 'blocked', $previousAttempts, null);
                $this->logReason($orderId, 'authentication_rejected');
                return;
            }
            if ($response === 'terminal') {
                $this->transition($orderId, 'failed', $attempts, null);
                $this->logReason($orderId, 'invalid_order_or_dealer');
                return;
            }
        } catch (\Throwable $e) {
            $this->logReason($orderId, 'transport_failure');
        }
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->transition($orderId, 'failed', $attempts, null);
            $this->logReason($orderId, 'retry_limit_reached');
        } else {
            $delay = max(self::DELAYS[$attempts - 1], $this->retryAfter);
            $this->transition($orderId, 'pending', $attempts, gmdate('Y-m-d H:i:s', time() + $delay));
        }
    }

    private function deliver($order, array $snapshot)
    {
        $storeId = (int) $order->getStoreId();
        $url = $this->helper->getFflApiUrl($storeId) . '/stores/' .
            rawurlencode($snapshot['storeHash']) . '/order-attributions';
        $client = $this->curlFactory->create();
        $client->setTimeout(8);
        $client->setOption(CURLOPT_CONNECTTIMEOUT, 3);
        $client->setOption(CURLOPT_FOLLOWLOCATION, false);
        $client->addHeader('Accept', 'application/json');
        $client->addHeader('Content-Type', 'application/json');
        $client->addHeader('Authorization', 'Bearer ' . $this->helper->getStoreSecret($storeId));
        $createdAt = new \DateTimeImmutable((string) $order->getCreatedAt(), new \DateTimeZone('UTC'));
        $client->post($url, json_encode([
            'order_id' => $storeId . ':' . $order->getIncrementId(),
            'dealer_id' => (int) $snapshot['id'],
            'ffl_license' => $snapshot['license'],
            'ordered_at' => $createdAt->format('Y-m-d\TH:i:s\Z')
        ]));
        $status = (int) $client->getStatus();
        if ($status === 401 || $status === 403) {
            return 'auth';
        }
        if ($status === 429 || $status >= 500) {
            $headers = (array) $client->getHeaders();
            $retryAfter = $headers['Retry-After'] ?? $headers['retry-after'] ?? null;
            if (is_numeric($retryAfter)) {
                $this->retryAfter = min(3600, max(0, (int) $retryAfter));
            } elseif (is_string($retryAfter) && ($retryAt = strtotime($retryAfter)) !== false) {
                $this->retryAfter = min(3600, max(0, $retryAt - time()));
            }
            return 'retry';
        }
        if ($status !== 200 && $status !== 201) {
            return 'terminal';
        }
        $body = json_decode((string) $client->getBody(), true);
        if (!is_array($body) || !in_array($body['result'] ?? null, ['recorded', 'duplicate'], true)) {
            return 'retry';
        }
        return $body['result'];
    }

    private function transition($orderId, $state, $attempts, $nextAt)
    {
        $connection = $this->orderResource->getConnection();
        $connection->update(
            $this->orderResource->getMainTable(),
            [
                'ffl_attribution_state' => $state,
                'ffl_attribution_attempts' => $attempts,
                'ffl_attribution_next_at' => $nextAt
            ],
            $connection->quoteInto('entity_id = ? AND ffl_attribution_state = \'pending\'', $orderId)
        );
    }

    private function logReason($orderId, $reason)
    {
        $this->logger->warning('AutoFFL order attribution not delivered', [
            'order_id' => $orderId,
            'reason' => $reason
        ]);
    }
}
