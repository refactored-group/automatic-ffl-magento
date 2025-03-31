<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Observer\Checkout;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
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

    private $request;

    public function __construct(
        Helper $helper,
        ManagerInterface $messageManager,
        Session $session,
        UrlInterface $url,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        Request $request
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->session = $session;
        $this->url = $url;
        $this->responseFactory = $responseFactory;
        $this->request = $request;
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
        if ($this->helper->isEnabled() && $this->helper->isMixedCart()) {
            if ($observer->getEvent()->getName() === 'controller_action_predispatch_checkout_index_index') {
                if ($this->helper->isMultishippingCheckoutAvailable()) {
                    return $observer->getControllerAction()
                        ->getResponse()
                        ->setRedirect($this->url->getUrl('multishipping/checkout'));
                } elseif (!$this->helper->shipNonGunItems()) {
                    return $observer->getControllerAction()
                        ->getResponse()
                        ->setRedirect($this->url->getUrl('checkout/cart/index'));
                }
            } elseif ($observer->getEvent()->getName() === 'controller_action_predispatch_checkout_cart_index') {
                if ($this->helper->isMultishippingCheckoutAvailable()) {
                    // @TODO: This message seems a little confusing, we need to work on a better one
                    $message  = __('Your cart has items that need to be shipped to a Dealer. '
                        . 'You can not perform a regular checkout with a mixed cart,'
                        . ' so we will redirect you to the Multi-Shipping Checkout.');
                } elseif (!$this->helper->shipNonGunItems()) {
                    $message  = __('Some items in your cart must be shipped to a Licensed Firearm Dealer (FFL). '
                        . 'To proceed, please remove non-FFL items and place a separate order for them.');
                } elseif ($this->helper->shipNonGunItems()) {
                    $message  = __('Your cart has items that need to be shipped to a Dealer. '
                        . "All items will be shipped together. You'll be requested to select a Dealer on the next step.");
                }
            } elseif ($observer->getEvent()->getName() === 'sales_order_place_before' && !$this->helper->shipNonGunItems()) {
                $message  = __('Your cart has items that need to be shipped to a Dealer. '
                    . 'You can not perform a regular checkout with a mixed cart. '
                    . 'Please, use the Multi-Shipping Checkout option.');
                $this->messageManager->addErrorMessage($message);
                $observer->getControllerAction()
                    ->getResponse()
                    ->setRedirect($this->url->getUrl('checkout/cart/index'));

                return;
            }

            if (!empty($message)) {
                $this->messageManager->addErrorMessage($message);
            }
        }
    }
}
