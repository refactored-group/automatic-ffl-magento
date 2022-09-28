<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Checkout\Controller\Index;

use Closure;
use Magento\Checkout\Controller\Index\Index as ParentControllor;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;

/**
 * Class Index
 */
class Index
{
    /** @var RedirectFactory */
    private $resultRedirectFactory;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @param RedirectFactory $resultRedirectFactory
     * @param Helper $helper
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        Helper $helper
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->helper = $helper;
    }

    /**
     * Redirects customers without a session to the multi-shipping cart
     * if the feature is enabled and has a FFL item in the quote
     *
     * @param ParentControllor $subject
     * @param Closure $proceed
     * @return ResultInterface
     */
    public function aroundExecute(ParentControllor $subject, Closure $proceed)
    {
        if ($this->helper->isMultishippingCheckoutAvailable() && $this->helper->isMixedCart()) {
            return $this->resultRedirectFactory->create()->setPath('multishipping/checkout');
        }

        return $proceed();
    }
}
