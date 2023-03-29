<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Model\Checkout\Type;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Session\Generic;
use Magento\Multishipping\Helper\Data;
use Magento\Payment\Model\Method\SpecificationInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Model\Quote\Address\ToOrder;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Quote\Model\Quote\Payment\ToOrderPayment;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;

class Multishipping extends \Magento\Multishipping\Model\Checkout\Type\Multishipping
{
    /**
     * @var AddressCollectionFactory
     */
    private $addressCollectionFactory;
    /**
     * @var array|bool
     */
    private $customerAddresses = false;

    /**
     * @param AddressCollectionFactory $addressCollectionFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Session $customerSession
     * @param OrderFactory $orderFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param ManagerInterface $eventManager
     * @param ScopeConfigInterface $scopeConfig
     * @param Generic $session
     * @param AddressFactory $addressFactory
     * @param ToOrder $quoteAddressToOrder
     * @param ToOrderAddress $quoteAddressToOrderAddress
     * @param ToOrderPayment $quotePaymentToOrderPayment
     * @param ToOrderItem $quoteItemToOrderItem
     * @param StoreManagerInterface $storeManager
     * @param SpecificationInterface $paymentSpecification
     * @param Data $helper
     * @param OrderSender $orderSender
     * @param PriceCurrencyInterface $priceCurrency
     * @param CartRepositoryInterface $quoteRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param TotalsCollector $totalsCollector
     * @param array $data
     * @param CartExtensionFactory|null $cartExtensionFactory
     * @param AllowedCountries|null $allowedCountryReader
     * @param \Magento\Multishipping\Model\Checkout\Type\Multishipping|null $placeOrderFactory
     * @param LoggerInterface|null $logger
     * @param DataObjectHelper|null $dataObjectHelper
     */
    public function __construct(
        AddressCollectionFactory $addressCollectionFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        Session $customerSession,
        OrderFactory $orderFactory,
        AddressRepositoryInterface $addressRepository,
        ManagerInterface $eventManager,
        ScopeConfigInterface $scopeConfig,
        Generic $session,
        AddressFactory $addressFactory,
        ToOrder $quoteAddressToOrder,
        ToOrderAddress $quoteAddressToOrderAddress,
        ToOrderPayment $quotePaymentToOrderPayment,
        ToOrderItem $quoteItemToOrderItem,
        StoreManagerInterface $storeManager,
        SpecificationInterface $paymentSpecification,
        Data $helper,
        OrderSender $orderSender,
        PriceCurrencyInterface $priceCurrency,
        CartRepositoryInterface $quoteRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        TotalsCollector $totalsCollector,
        array $data = [],
        CartExtensionFactory $cartExtensionFactory = null,
        AllowedCountries $allowedCountryReader = null,
        \Magento\Multishipping\Model\Checkout\Type\Multishipping $placeOrderFactory = null,
        LoggerInterface $logger = null,
        DataObjectHelper $dataObjectHelper = null
    ) {
        $this->addressCollectionFactory = $addressCollectionFactory;

        \Magento\Multishipping\Model\Checkout\Type\Multishipping::__construct(
            $checkoutSession,
            $customerSession,
            $orderFactory,
            $addressRepository,
            $eventManager,
            $scopeConfig,
            $session,
            $addressFactory,
            $quoteAddressToOrder,
            $quoteAddressToOrderAddress,
            $quotePaymentToOrderPayment,
            $quoteItemToOrderItem,
            $storeManager,
            $paymentSpecification,
            $helper,
            $orderSender,
            $priceCurrency,
            $quoteRepository,
            $searchCriteriaBuilder,
            $filterBuilder,
            $totalsCollector,
            $data,
            $cartExtensionFactory,
            $allowedCountryReader,
            $placeOrderFactory,
            $logger,
            $dataObjectHelper
        );
    }

    /**
     * Check if specified address ID belongs to customer.
     *
     * @param mixed $addressId
     * @return bool
     */
    protected function isAddressIdApplicable($addressId)
    {
        if (!$this->customerAddresses) {
            $collection = $this->addressCollectionFactory->create();
            /**
             * Get all addresses for this customer but ignoring the is_delete attribute filter added
             * by default. See \RefactoredGroup\AutoFflCore\Plugin\AddressCollectionFactoryPlugin
             */
            $collection->getSelect()->reset('where');
            $collection->setCustomerFilter($this->getCustomer());

            $this->customerAddresses = $collection->getAllIds();
        }

        return in_array($addressId, $this->customerAddresses);
    }
}
