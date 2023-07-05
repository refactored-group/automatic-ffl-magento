<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout\Address;

use Closure;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Multishipping\Controller\Checkout\Address\EditShipping as ParentControllor;
use Magento\Customer\Api\AddressRepositoryInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\App\Request\Http as Request;

/**
 * Plugin for the controller EditShipping, responsible
 * for editing the shipping addresses on the MultiShipping Checkout
 */
class EditShipping
{
    /** @var RedirectFactory */
    private $resultRedirectFactory;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param RedirectFactory $resultRedirectFactory
     * @param Helper $helper
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        Helper $helper,
        Context $context,
        AddressRepositoryInterface $addressRepository,
        Request $request
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->helper = $helper;
        $this->context = $context;
        $this->addressRepository = $addressRepository;
        $this->request = $request;
    }

    /**
     * Redirects customers back to the multi-shipping initial page if
     * they try to edit an address when a FFL item is in the cart
     *
     * @param ParentControllor $subject
     * @param Closure $proceed
     * @return ResultInterface
     */
    public function aroundExecute(ParentControllor $subject, Closure $proceed)
    {
        $redirect = false;
        // Verifies if this is a ffl address
        if ($addressId = $this->request->getParam('id')) {
            try {
                $address = $this->addressRepository->getById($addressId);
                $isDeleted = $address->getCustomAttribute('is_deleted');
                if ($isDeleted && $isDeleted->getValue() === '1') {
                    $redirect = true;
                }
            } catch (NoSuchEntityException $e) {
                $redirect = true;
            }
        }
        if ($redirect) {
            $this->context->getMessageManager()->addErrorMessage(
                __("You can not edit a dealer's address")
            );
            return $this->resultRedirectFactory->create()->setPath('multishipping/checkout/shipping');
        }

        $proceed();
    }
}
