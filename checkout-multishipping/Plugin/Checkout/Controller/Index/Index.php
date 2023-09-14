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
use RefactoredGroup\AutoFflCheckoutMultiShipping\Helper\Data as MsHelper;

class Index
{
    /** @var RedirectFactory */
    private $resultRedirectFactory;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var MsHelper
     */
    private $msHelper;

    /**
     * @param RedirectFactory $resultRedirectFactory
     * @param Helper $helper
     * @param MsHelper $msHelper
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        Helper $helper,
        MsHelper $msHelper
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->helper = $helper;
        $this->msHelper = $msHelper;
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
        if ($this->helper->isMultishippingCheckoutAvailable()) {
            $this->msHelper->clearCustomerSession();
            if($this->helper->isMixedCart()) {
                return $this->resultRedirectFactory->create()->setPath('multishipping/checkout');
            }
        }

        return $proceed();
    }
}
