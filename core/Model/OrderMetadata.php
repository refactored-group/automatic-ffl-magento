<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Sales\Model\Order;

class OrderMetadata
{
    private $fflData;

    public function __construct(OrderFflData $fflData)
    {
        $this->fflData = $fflData;
    }

    public function apply(Order $order, array $snapshot)
    {
        if ($order->getFflDealerData()) {
            return;
        }
        $order->setFflLicense($snapshot['license']);
        $order->setFflDealerData(json_encode($snapshot));

        $comment = $this->fflData->formatComment($this->fflData->fromSnapshot($snapshot));
        $history = $order->addStatusHistoryComment($comment, false);
        $history->setIsVisibleOnFront(false);
        $history->setIsCustomerNotified(false);
    }
}
