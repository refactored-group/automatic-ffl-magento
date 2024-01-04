<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Checkout\Controller\Cart;

use Closure;
use Magento\Checkout\Controller\Cart\Index as ParentController;
use Magento\Customer\Model\Session;

class Index
{
    private const FFL_CHECKOUT_BUTTON_KEY = 'ffl_checkout_button_clicked';

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @param Session $customerSession
     */
    public function __construct(
        Session $customerSession
    ) {
        $this->customerSession = $customerSession;
    }

    /**
     * Redirects customers without a session to the multi-shipping cart
     * if the feature is enabled and has a FFL item in the quote
     *
     * @param ParentController $subject
     * @param Closure $proceed
     * @return ResultInterface
     */
    public function aroundExecute(ParentController $subject, Closure $proceed)
    {
        // Clear the customerSession in Shopping Cart page.
        $this->customerSession->unsetData(self::FFL_CHECKOUT_BUTTON_KEY);

        return $proceed();
    }
}
