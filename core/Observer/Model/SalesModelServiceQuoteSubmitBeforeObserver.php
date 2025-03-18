<?php

namespace RefactoredGroup\AutoFflCore\Observer\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class SalesModelServiceQuoteSubmitBeforeObserver implements ObserverInterface
{
    private $orderRepository;
    
    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    public function execute(Observer $observer)
    {
        $quote = $observer->getData('quote');
        $order = $observer->getData('order');
        $fflLicense = $quote->getFflLicense() ?? null;
        $order->setFflLicense($fflLicense);
        $this->orderRepository->save($order);
    }
}

