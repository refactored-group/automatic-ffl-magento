<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace Razoyo\AutoFflCore\Observer\Checkout;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Razoyo\AutoFflCore\Helper\Data as Helper;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;

class Index implements ObserverInterface
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
     * @var Session
     */
    private $session;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    private $responseFactory;
    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper,
        ManagerInterface $messageManager,
        Session $session,
        UrlInterface $url,
        \Magento\Framework\App\ResponseFactory $responseFactory
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->session = $session;
        $this->url = $url;
        $this->responseFactory = $responseFactory;
    }

    /**
     * When FFL is enabled, verifies if all items in the cart are to be shipped by FFL.
     * If the cart is mixed with non-FFL items, redirect the customer to the shopping cart controller
     * and display an error message
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->helper->isEnabled() && $this->helper->hasFflItem() && !$this->helper->isFflCart()) {
            $message  = 'Your cart has items that need to be shipped to a Dealer. ';
            if (!$this->helper->isMultishippingCheckoutAvailable()) {
                // @TODO: This message seems a little confusing, we need to work on a better one
                $message .= 'You can not checkout with a mixed cart. ';
                $message .= 'Please remove all items from your cart that need to be shipped to a Dealer or the items that do not.';
                $this->messageManager->addErrorMessage(__($message));
            }

            if ($observer->getEvent()->getName() == 'sales_order_place_before') {
                echo $this->url->getUrl('checkout/cart/index');
                exit;
            } else if ($observer->getEvent()->getName() !== 'controller_action_predispatch_checkout_cart_index') {
                $observer->getControllerAction()
                    ->getResponse()
                    ->setRedirect($this->url->getUrl('multishipping/checkout'));
            }
        }
    }
}
