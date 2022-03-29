<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace Razoyo\AutoFflCheckoutMultiShipping\Controller\Index;

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

class Index extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    const DEFAULT_COUNTRY_CODE = 'US';
    const DEFAULT_LAST_NAME = '.';

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
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->_rawFactory = $rawFactory;
        $this->addressRepository = $addressRepository;
        $this->addressDataFactory = $addressDataFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customerSession = $customerSession;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->request = $request;

        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Raw|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $data = $this->request->getParams();
        $result = $this->_rawFactory->create();

        // Look for State ID
        $region = $this->regionCollectionFactory->create();
        $region->addFieldToFilter('country_id', ['eq' => self::DEFAULT_COUNTRY_CODE]);
        $region->addFieldToFilter('code', ['eq' => $data['premise_state']]);
        $state = $region->getFirstItem();

        //@TODO: Improve the way this is communicated with the user
        if (!$state->getDataByKey('region_id')) {
            throw new LocalizedException(__('FFL State ID not found.'));
        }
        /**
         * Customer has to be logged in order to use multi shipping checkout
         */
        $customerId = $this->customerSession->getCustomerId();

        $address = $this->addressDataFactory->create();
        $address->setFirstname($data['business_name'])
            ->setLastname(self::DEFAULT_LAST_NAME)
            ->setCountryId(self::DEFAULT_COUNTRY_CODE)
            ->setRegionId($state->getDataByKey('region_id'))
            ->setRegion(null)
            ->setCity($data['premise_city'])
            ->setPostcode($data['premise_zip'])
            ->setCustomerId($customerId)
            ->setStreet([$data['premise_street']])
            ->setTelephone($data['phone_number'])
            ->setCustomAttribute('is_deleted', 1);

        /** @var  \Magento\Customer\Api\Data\AddressInterface $address */
        $address = $this->addressRepository->save($address);

        $stringAddress = sprintf('%s, %s, %s, %s %s',
            $data['business_name'], $data['premise_street'], $data['premise_city'], $data['premise_state'], $data['premise_zip']);

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
