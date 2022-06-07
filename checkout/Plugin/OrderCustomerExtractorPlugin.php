<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace Razoyo\AutoFflCheckout\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Razoyo\AutoFflCore\Helper\Data as Helper;

class OrderCustomerExtractorPlugin
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param Helper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Helper $helper,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param \Magento\Sales\Model\Order\OrderCustomerExtractor $subject
     * @param $result
     * @param $orderId
     * @return array
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterExtract(\Magento\Sales\Model\Order\OrderCustomerExtractor $subject, $result, $orderId)
    {
        $quoteId = $this->orderRepository->get($orderId)->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);
        /**
         * If this order has a FFL item, we can not use the shipping address
         * when creating a new account
         */
        if ($this->helper->isEnabled() && $this->helper->hasFflItem($quote)) {
            $addresses = $result->getAddresses();
            $newAddresses = [];

            foreach ($addresses as $address) {
                if ($address->isDefaultBilling()) {
                    $address->setIsDefaultShipping(true);
                    $newAddresses[] = $address;
                    break;
                }
            }
            $result->setAddresses($newAddresses);
        }

        return $result;
    }
}
