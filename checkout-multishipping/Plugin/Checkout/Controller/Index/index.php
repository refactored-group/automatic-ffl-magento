<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace Razoyo\AutoFflCheckoutMultiShipping\Plugin\Checkout\Controller\Index;

use Closure;
use Magento\Checkout\Controller\Index\Index as ParentControllor;
use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Razoyo\AutoFflCheckoutMultiShipping\Helper\Data as MultishippingHelper;

/**
 * Class Index
 */
class Index
{
    /** @var Data */
    private $checkoutHelper;

    /** @var CustomerSession */
    private $customerSession;

    /** @var RequestInterface */
    private $request;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var RedirectFactory */
    private $resultRedirectFactory;

    /** @var Onepage */
    private $onepage;

    /** @var Session */
    private $checkoutSession;

    /** @var PageFactory */
    private $resultPageFactory;
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $multishippingHelper;

    /**
     * @param PageFactory $resultPageFactory
     * @param Session $checkoutSession
     * @param Onepage $onepage
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param Data $checkoutHelper
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageFactory $resultPageFactory,
        Session $checkoutSession,
        Onepage $onepage,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        RequestInterface $request,
        CustomerSession $customerSession,
        Data $checkoutHelper,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        MultishippingHelper $multishippingHelper
    ) {
        $this->checkoutHelper = $checkoutHelper;
        $this->customerSession = $customerSession;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->onepage = $onepage;
        $this->checkoutSession = $checkoutSession;
        $this->resultPageFactory = $resultPageFactory;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->multishippingHelper = $multishippingHelper;
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
        if ($this->multishippingHelper->isMultishippingCheckoutAvailable()) {
            return $this->resultRedirectFactory->create()->setPath('multishipping/checkout');
        }

        return $proceed();
    }
}
