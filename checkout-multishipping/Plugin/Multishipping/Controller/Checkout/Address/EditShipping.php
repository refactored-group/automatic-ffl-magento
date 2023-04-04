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
use Magento\Multishipping\Controller\Checkout\Address\EditShipping as ParentControllor;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;

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
     * @param RedirectFactory $resultRedirectFactory
     * @param Helper $helper
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        Helper $helper,
        Context $context
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->helper = $helper;
        $this->context = $context;
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
        if ($this->helper->isMultishippingCheckoutAvailable() && $this->helper->hasFflItem()) {
            $this->context->getMessageManager()->addErrorMessage(
                __("You can not edit a dealer's address")
            );

            return $this->resultRedirectFactory->create()->setPath('multishipping/checkout');
        }
        $proceed();
    }
}
