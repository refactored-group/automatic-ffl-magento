<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Plugin\Checkout\Controller\Index;

use Closure;
use Magento\Checkout\Controller\Index\Index as ParentControllor;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use RefactoredGroup\AutoFflCheckoutMultiShipping\Helper\Data as MsHelper;

class Index
{
    private const FFL_CHECKOUT_BUTTON_KEY = 'ffl_checkout_button_clicked';

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
     * @var Session
     */
    private $customerSession;

    /**
     * @param RedirectFactory $resultRedirectFactory
     * @param Helper $helper
     * @param MsHelper $msHelper
     * @param Session $customerSession
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        Helper $helper,
        MsHelper $msHelper,
        Session $customerSession
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->helper = $helper;
        $this->msHelper = $msHelper;
        $this->customerSession = $customerSession;
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
            if ($this->helper->isMixedCart()) {
                /**
                 * Normal checkout redirects mixed FFL carts into multishipping,
                 * where FFL and non-FFL rows should behave as grouped sections.
                 */
                $this->customerSession->setData(
                    self::FFL_CHECKOUT_BUTTON_KEY,
                    'proceed_to_checkout'
                );

                return $this->resultRedirectFactory->create()->setPath('multishipping/checkout');
            }
        }

        $this->customerSession->unsetData(self::FFL_CHECKOUT_BUTTON_KEY);

        return $proceed();
    }
}
