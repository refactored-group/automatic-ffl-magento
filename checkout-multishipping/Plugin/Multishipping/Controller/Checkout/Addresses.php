<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout;

use Closure;
use Magento\Framework\Controller\Result\RedirectFactory;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\App\Action\Context;

/**
 * Class Addresses
 * Implements plugin to show a message from FFL to a customer buying a FFL item
 */
class Addresses
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper,
        Context $context,
        RedirectFactory $resultRedirectFactory
    ) {
        $this->helper = $helper;
        $this->context = $context;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * Add a message telling the customer that he will be required to choose a dealer
     * if the feature is enabled and has a FFL item in the quote
     *
     * @param \Magento\Multishipping\Controller\Checkout\Addresses $subject
     * @param Closure $proceed
     * @return mixed
     */
    public function aroundExecute(\Magento\Multishipping\Controller\Checkout\Addresses $subject, Closure $proceed)
    {
        if (!$this->helper->isMultishippingCheckoutAvailable()) {
            // Redirect to the normal cart
            return $this->resultRedirectFactory->create()->setPath('checkout/index');
        } else {
            if ($this->helper->hasFflItem()) {
                $this->context->getMessageManager()->addNoticeMessage(
                    __('You have a firearm in your cart and must choose a '
                        . 'Licensed Firearm Dealer (FFL) for the shipping address(es).')
                );
            }
        }

        return $proceed();
    }
}
