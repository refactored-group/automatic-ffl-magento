<?php

namespace RefactoredGroup\AutoFflCore\Observer\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use RefactoredGroup\AutoFflCore\Model\OrderMetadata;

class SalesModelServiceQuoteSubmitBeforeObserver implements ObserverInterface
{
    private $metadata;
    
    public function __construct(
        OrderMetadata $metadata
    ) {
        $this->metadata = $metadata;
    }

    public function execute(Observer $observer)
    {
        $quote = $observer->getData('quote');
        $order = $observer->getData('order');
        $data = json_decode((string) $quote->getFflDealerData(), true);
        if (is_array($data) && isset($data['standard']['license'])) {
            $this->metadata->apply($order, $data['standard']);
        } elseif ($quote->getFflLicense()) {
            $order->setFflLicense($quote->getFflLicense());
        }
    }
}
