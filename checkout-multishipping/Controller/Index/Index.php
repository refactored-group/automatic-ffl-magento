<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Controller\Index;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context as ContextAlias;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use RefactoredGroup\AutoFflCore\Model\DealerSnapshot;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;

class Index extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    const DEFAULT_COUNTRY_CODE = 'US';

    /**
     * @var RawFactory
     */
    protected $_rawFactory;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressDataFactory;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var RegionCollectionFactory
     */
    private $regionCollectionFactory;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var RequestInterface
     */
    private $request;
    private $checkoutSession;
    private $quoteRepository;
    private $snapshot;
    private $helper;

    /**
     * @param ContextAlias $context
     * @param RawFactory $rawFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressInterfaceFactory $addressDataFactory
     * @param CollectionFactory $productCollectionFactory
     * @param Session $customerSession
     * @param RegionCollectionFactory $regionCollectionFactory
     */
    public function __construct(
        ContextAlias                            $context,
        RawFactory                              $rawFactory,
        AddressRepositoryInterface              $addressRepository,
        AddressInterfaceFactory                 $addressDataFactory,
        CollectionFactory                       $productCollectionFactory,
        Session                                 $customerSession,
        RegionCollectionFactory                 $regionCollectionFactory,
        FormKeyValidator                        $formKeyValidator,
        \Magento\Framework\App\RequestInterface $request,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        DealerSnapshot $snapshot,
        Helper $helper
    ) {
        $this->_rawFactory = $rawFactory;
        $this->addressRepository = $addressRepository;
        $this->addressDataFactory = $addressDataFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customerSession = $customerSession;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->snapshot = $snapshot;
        $this->helper = $helper;

        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Raw|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $result = $this->_rawFactory->create();
        if (!$this->request->isPost()) {
            $this->getResponse()->setHttpResponseCode(405);
            return $result->setContents('POST required');
        }
        $customerId = (int) $this->customerSession->getCustomerId();
        $quote = $this->checkoutSession->getQuote();
        if ($customerId <= 0 || !$quote->getId() || !$quote->getIsActive() ||
            (int) $quote->getCustomerId() !== $customerId) {
            throw new LocalizedException(__('Sign in and refresh the active cart before selecting a dealer.'));
        }
        if (!$this->helper->isEnabled((int) $quote->getStoreId()) || !$this->helper->hasFflItem($quote)) {
            throw new LocalizedException(__('This cart does not require a dealer selection.'));
        }
        $data = $this->request->getParams();
        $snapshot = $this->snapshot->fromJson(
            $data['ffl_dealer_data'] ?? null,
            $data['license'] ?? null,
            (int) $quote->getStoreId()
        );
        $snapshots = json_decode((string) $quote->getFflDealerData(), true);
        if (!is_array($snapshots)) {
            $snapshots = [];
        }
        $replacedAddressId = (string) ($data['replaces_address_id'] ?? '');
        if (preg_match('/^[1-9][0-9]*$/', $replacedAddressId) &&
            isset($snapshots['addresses'][$replacedAddressId])) {
            unset($snapshots['addresses'][$replacedAddressId]);
        }
        if (count($snapshots['addresses'] ?? []) >= 100 ||
            strlen(json_encode($snapshots)) + strlen(json_encode($snapshot)) + 32 > 60000) {
            throw new LocalizedException(__('Too many dealer selections are saved on this cart. Refresh the cart and try again.'));
        }

        // Look for State ID
        $region = $this->regionCollectionFactory->create();
        $region->addFieldToFilter('country_id', ['eq' => self::DEFAULT_COUNTRY_CODE]);
        $region->addFieldToFilter('code', ['eq' => $snapshot['state']]);
        $state = $region->getFirstItem();

        //@TODO: Improve the way this is communicated with the user
        if (!$state->getDataByKey('region_id')) {
            throw new LocalizedException(__('FFL State ID not found.'));
        }
        /**
         * Customer has to be logged in order to use multi shipping checkout
         */
        $address = $this->addressDataFactory->create();
        $address->setFirstname($snapshot['firstName'] ?: Helper::DEFAULT_FIRSTNAME)
            ->setLastname($snapshot['lastName'] ?: Helper::DEFAULT_LASTNAME)
            ->setCompany($snapshot['company'])
            ->setCountryId(self::DEFAULT_COUNTRY_CODE)
            ->setRegionId($state->getDataByKey('region_id'))
            ->setRegion(null)
            ->setCity($snapshot['city'])
            ->setPostcode($snapshot['postalCode'])
            ->setCustomerId($customerId)
            ->setStreet(array_values(array_filter([$snapshot['address1'], $snapshot['address2']], 'strlen')))
            ->setTelephone($snapshot['phone'])
            ->setCustomAttribute('is_deleted', 1);

        $customer = $this->customerSession->getCustomer();
        if($customerId && $customer && !$snapshot['firstName'] && !$snapshot['lastName']) {
            $firstname = $customer->getFirstname();
            $lastname = $customer->getLastname();
            $address->setFirstname($firstname)
                ->setLastname($lastname);
            $fullname = $firstname . ' ' . $lastname;
        } else {
            $fullname = $address->getFirstname() . ' ' . $address->getLastname();
        }

        /** @var  \Magento\Customer\Api\Data\AddressInterface $address */
        $address = $this->addressRepository->save($address);

        $snapshots['addresses'][(string) $address->getId()] = $snapshot;
        $quote->setFflDealerData(json_encode($snapshots));
        $this->quoteRepository->save($quote);

        /* This is used to set the license to the order */
        $this->customerSession->setData('ffl_license_'.$address->getId(), $snapshot['license']);

        $stringAddress = sprintf(
            '%s, %s, %s, %s, %s %s',
            $fullname,
            $snapshot['company'],
            $snapshot['address1'],
            $snapshot['city'],
            $snapshot['state'],
            $snapshot['postalCode']
        );

        $result->setContents(json_encode(['id' => $address->getId(), 'name' => $stringAddress]));

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate form key to prevent against CSRF
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        if ($this->formKeyValidator->validate($this->request)) {
            return true;
        }

        return false;
    }
}
