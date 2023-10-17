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
use Magento\Framework\App\ObjectManager;
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
     * Core event manager proxy
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager = null;

    /**
     * Initialize dependencies.
     *
     * @var \Magento\Multishipping\Helper\Data
     */
    protected $helper;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var \Magento\Quote\Api\Data\CartExtensionFactory
     */
    private $cartExtensionFactory;

    /**
     * @var \Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor
     */
    private $shippingAssignmentProcessor;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

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
        \Magento\Framework\App\RequestInterface $request,
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
        $this->request = $request;
        $this->_eventManager = $eventManager;
        $this->helper = $helper;
        $this->addressRepository = $addressRepository;
        $this->cartExtensionFactory = $cartExtensionFactory;

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

    /**
     * Assign quote items to addresses and specify items qty
     *
     * Array structure:
     * array(
     *      $quoteItemId => array(
     *          'qty'       => $qty,
     *          'address'   => $customerAddressId
     *      )
     * )
     *
     * @param array $info
     * @return \Magento\Multishipping\Model\Checkout\Type\Multishipping
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function setShippingItemsInformation($info)
    {
        if (is_array($info)) {
            $allQty = 0;
            $itemsInfo = [];
            foreach ($info as $itemData) {
                foreach ($itemData as $quoteItemId => $data) {
                    $allQty += $data['qty'];
                    $itemsInfo[$quoteItemId] = $data;
                }
            }

            $maxQty = $this->helper->getMaximumQty();
            if ($allQty > $maxQty) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        "The maximum quantity can't be more than %1 when shipping to multiple addresses. "
                        . "Change the quantity and try again.",
                        $maxQty
                    )
                );
            }
            $quote = $this->getQuote();
            $addresses = $quote->getAllShippingAddresses();
            $newAddress = (bool)$this->request->getParam('new_address');
            /**
             * CONTEXT:
             * We enabled adding a new shipping address and disabled the
             * validation check on FFL item/s with empty address field
             * on shipping page.
             * 
             * When you try to enter a new address and then go back
             * to the shipping page, all FFL items get deleted
             * because of empty address ID.
             * 
             * So we check if the request parameters has new_address flag enabled.
             * This will skip "resetting" the customer shipping address.
             */
            if (!$newAddress) {
                foreach ($addresses as $address) {
                    /**
                     * This line deletes all associated customer
                     * shipping address from the quote_address_item table.
                     * 
                     * The effect after deleting the address/es, when the customer
                     * goes back to the shipping page, all related FFL item/s
                     * will not be displayed on the page but when you check the items
                     * in the quote_item table, the record/s are still there.
                     */
                    $quote->removeAddress($address->getId());
                }
            }
            
            if (!$newAddress) {
                foreach ($info as $itemData) {
                    foreach ($itemData as $quoteItemId => $data) {
                        /**
                         * This line inserts a new address in the
                         * quote_address_item table.
                         * 
                         * It will only insert a record if the $addressId
                         * is present from the request. So for FFL item/s
                         * that doesn't have an address set, it will not
                         * be added back to the table.
                         */
                        $this->_addShippingItem($quoteItemId, $data);
                    }
                }
            }

            $this->prepareShippingAssignment($quote);

            /**
             * Delete all not virtual quote items which are not added to shipping address
             * MultishippingQty should be defined for each quote item when it processed with _addShippingItem
             */
            if (!$newAddress) {
                foreach ($quote->getAllItems() as $_item) {
                    if (!$_item->getProduct()->getIsVirtual() && !$_item->getParentItem() && !$_item->getMultishippingQty()
                    ) {
                        /**
                         * This line removes associated records
                         * from the quote_item table.
                         * 
                         * It automatically deletes FFL item/s that
                         * doesn't have a shipping address.
                         */
                        $quote->removeItem($_item->getId());
                    }
                }
            }

            $billingAddress = $quote->getBillingAddress();
            if ($billingAddress) {
                $quote->removeAddress($billingAddress->getId());
            }

            $customerDefaultBillingId = $this->getCustomerDefaultBillingAddress();
            if ($customerDefaultBillingId) {
                $quote->getBillingAddress()->importCustomerAddressData(
                    $this->addressRepository->getById($customerDefaultBillingId)
                );
            }

            foreach ($quote->getAllItems() as $_item) {
                if (!$_item->getProduct()->getIsVirtual()) {
                    continue;
                }

                if (isset($itemsInfo[$_item->getId()]['qty'])) {
                    $qty = (int)$itemsInfo[$_item->getId()]['qty'];
                    if ($qty) {
                        $_item->setQty($qty);
                        $quote->getBillingAddress()->addItem($_item);
                    } else {
                        $_item->setQty(0);
                        $quote->removeItem($_item->getId());
                    }
                }
            }

            $this->save();
            $this->_eventManager->dispatch('checkout_type_multishipping_set_shipping_items', ['quote' => $quote]);
        }
        return $this;
    }

    /**
     * Prepare shipping assignment.
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return \Magento\Quote\Model\Quote
     */
    private function prepareShippingAssignment($quote)
    {
        $cartExtension = $quote->getExtensionAttributes();
        if ($cartExtension === null) {
            $cartExtension = $this->cartExtensionFactory->create();
        }
        /** @var \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $this->getShippingAssignmentProcessor()->create($quote);
        $shipping = $shippingAssignment->getShipping();

        $shipping->setMethod(null);
        $shippingAssignment->setShipping($shipping);
        $cartExtension->setShippingAssignments([$shippingAssignment]);
        return $quote->setExtensionAttributes($cartExtension);
    }

    /**
     * Get shipping assignment processor.
     *
     * @return \Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor
     */
    private function getShippingAssignmentProcessor()
    {
        if (!$this->shippingAssignmentProcessor) {
            $this->shippingAssignmentProcessor = ObjectManager::getInstance()
                ->get(\Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor::class);
        }
        return $this->shippingAssignmentProcessor;
    }
}
