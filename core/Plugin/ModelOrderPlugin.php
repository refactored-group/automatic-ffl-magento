<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace Razoyo\AutoFflCore\Plugin;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Message\ManagerInterface;
use Razoyo\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\UrlInterface;

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
    function beforePlace(\Magento\Sales\Model\Order $subject)
    {
        if ($this->helper->isEnabled() && $this->helper->hasFflItem() && !$this->helper->isFflCart() && $this->request->getModuleName() != 'multishipping') {
            $message  = 'Your cart has items that need to be shipped to a Dealer. ';
            // @TODO: This message seems a little confusing, we need to work on a better one
            $message .= 'You can not checkout with a mixed cart. ';
            $message .= 'Please remove all items from your cart that need to be shipped to a Dealer or the items that do not.';
            $this->messageManager->addErrorMessage(__($message));
            $this->responseFactory->create()->setRedirect($this->url->getUrl('checkout/cart/index'))->sendResponse();
            exit;
        }
    }
}
