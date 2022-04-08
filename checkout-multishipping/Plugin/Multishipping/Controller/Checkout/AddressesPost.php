<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace Razoyo\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout;

use Closure;
use Razoyo\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\App\Action\Context;

/**
 * Class Addresses
 */
class AddressesPost
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Context
     */
    private $context;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper,
        Context $context
    ) {
        $this->helper = $helper;
        $this->context = $context;
    }

    /**
     * Verify if a FFL has been chosen for each firewarm.
     * If  not, redirect back to the addresses page step.
     *
     * @param \Magento\Multishipping\Controller\Checkout\AddressesPost $subject
     * @param Closure $proceed
     * @return mixed
     */
    public function aroundExecute(\Magento\Multishipping\Controller\Checkout\AddressesPost $subject, Closure $proceed)
    {
        if ($this->helper->hasFflItem()) {
            $items = $this->helper->getCustomerQuote()->getAllVisibleItems();

            if (count($items) > count($this->context->getRequest()->getPost('ship'))) {
                $this->context->getMessageManager()->addErrorMessage(
                    __('Please, select a Licensed Firearm Dealer for the following product(s): ') . $this->helper->getFflItemsNames());
                return $this->resultRedirectFactory->create()->setPath('multishipping/checkout');
            }
        }

        return $proceed();
    }
}
