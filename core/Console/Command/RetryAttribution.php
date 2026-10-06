<?php
namespace RefactoredGroup\AutoFflCore\Console\Command;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use RefactoredGroup\AutoFflCore\Helper\Data;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RetryAttribution extends Command
{
    private $orders;
    private $resource;
    private $helper;

    public function __construct(OrderRepositoryInterface $orders, OrderResource $resource, Data $helper)
    {
        parent::__construct();
        $this->orders = $orders;
        $this->resource = $resource;
        $this->helper = $helper;
    }

    protected function configure()
    {
        $this->setName('autoffl:attribution:retry')
            ->setDescription('Retry one blocked or failed AutoFFL Magento order attribution')
            ->addArgument('order_id', InputArgument::REQUIRED, 'Magento sales_order entity ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orderId = (string) $input->getArgument('order_id');
        if (!preg_match('/^[1-9][0-9]*$/', $orderId)) {
            $output->writeln('<error>Use a positive Magento order entity ID.</error>');
            return 1;
        }
        $order = $this->orders->get((int) $orderId);
        $snapshot = json_decode((string) $order->getFflDealerData(), true);
        $storeId = (int) $order->getStoreId();
        if (!in_array($order->getFflAttributionState(), ['blocked', 'failed'], true) ||
            !is_array($snapshot) || !isset($snapshot['storeHash'], $snapshot['sandbox']) ||
            $snapshot['storeHash'] !== $this->helper->getStoreHash($storeId) ||
            (bool) $snapshot['sandbox'] !== $this->helper->isSandboxMode($storeId) ||
            !$this->helper->getStoreSecret($storeId)) {
            $output->writeln('<error>Order is not eligible for retry or its original store configuration does not match.</error>');
            return 1;
        }
        $connection = $this->resource->getConnection();
        $affected = $connection->update(
            $this->resource->getMainTable(),
            [
                'ffl_attribution_state' => 'pending',
                'ffl_attribution_attempts' => 0,
                'ffl_attribution_next_at' => gmdate('Y-m-d H:i:s')
            ],
            $connection->quoteInto(
                'entity_id = ? AND ffl_attribution_state IN (\'blocked\', \'failed\')',
                (int) $orderId
            )
        );
        $output->writeln($affected ? 'Attribution queued for retry.' : 'Order state changed before retry.');
        return $affected ? 0 : 1;
    }
}
