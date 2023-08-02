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
     * @var HistoryFactory
     */
    private $orderHistoryFactory;

    /**
     * CookieManager
     *
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * CookieMetadata
     *
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

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
        Request $request,
        HistoryFactory $orderHistoryFactory,
        LoggerInterface $logger,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->url = $url;
        $this->responseFactory = $responseFactory;
        $this->request = $request;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->logger = $logger;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @return void
     */
    public function beforePlace(\Magento\Sales\Model\Order $subject)
    {
        if ($this->helper->isEnabled() && $this->helper->hasFflItem() && !$this->helper->isFflCart()
            && $this->request->getModuleName() != 'multishipping' && !$this->helper->shipNonGunItems()) {
            // @TODO: This message seems a little confusing, we need to work on a better one
            $message  = __('Your cart has items that need to be shipped to a Dealer. '
                . 'You can not checkout with a mixed cart. '
                . 'Please remove all items from your cart that need to be shipped '
                . 'to a Dealer or the items that do not.');

            $this->messageManager->addErrorMessage($message);
            $this->responseFactory->create()->setRedirect($this->url->getUrl('checkout/cart/index'))->sendResponse();

            return;
        }
    }


    public function afterPlace(Order $subject, Order $result) {

        if ($this->helper->isEnabled() && $this->helper->hasFflItem() && $this->helper->isFflCart()
            && $this->request->getModuleName() != 'multishipping' && !$this->helper->shipNonGunItems()) {
                
                $address = $result->getShippingAddress();
                
                if ($address) {

                    $cookieName = 'FFL_Dealer_Id';

                    $dealerId = $this->cookieManager->getCookie($cookieName);

                    try {

                        $history = $this->orderHistoryFactory->create()
                            ->setEntityName(\Magento\Sales\Model\Order::ENTITY) // Set the entity name for order
                            ->setComment(
                                __('FFL: %1', $dealerId)
                            );

                        $history->setIsCustomerNotified(false)
                        ->setIsVisibleOnFront(true);

                        $result->addStatusHistory($history);
                        
                        $cookieMetadata = $this->cookieMetadataFactory
                        ->createPublicCookieMetadata()
                        ->setDuration(0)->setPath('/');
                        
                        $this->cookieManager->deleteCookie($cookieName, $cookieMetadata);

                    } catch (NoSuchEntityException $exception) {
                        $this->logger->error($exception->getMessage());
                    }
                    return $result;
            }
        }
        return;
    }
}
