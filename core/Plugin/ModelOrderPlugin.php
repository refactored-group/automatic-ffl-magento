<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Plugin;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class ModelOrderPlugin
{
    /**
     * @var Helper
     */
    private $helper;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var ResponseFactory
     */
    private $responseFactory;
    /**
     * @var Request
     */
    private $request;

    /**
     * @param Helper $helper
     * @param ManagerInterface $messageManager
     * @param UrlInterface $url
     * @param ResponseFactory $responseFactory
     * @param Request $request
     */
    public function __construct(
        Helper $helper,
        ManagerInterface $messageManager,
        UrlInterface $url,
        ResponseFactory $responseFactory,
        Request $request
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->url = $url;
        $this->responseFactory = $responseFactory;
        $this->request = $request;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @return void
     */
    public function beforePlace(\Magento\Sales\Model\Order $subject)
    {
        if ($this->helper->isEnabled() && $this->helper->hasFflItem() && !$this->helper->isFflCart()
            && $this->request->getModuleName() != 'multishipping' && !$this->helper->shipNonGunItems()) {
            $message  = __('Some items in your cart must be shipped to a Licensed Firearm Dealer (FFL). '
                        . 'To proceed, please remove non-FFL items and place a separate order for them.');

            $this->messageManager->addErrorMessage($message);
            $this->responseFactory->create()->setRedirect($this->url->getUrl('checkout/cart/index'))->sendResponse();

            return;
        }
    }
}
