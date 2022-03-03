<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace Razoyo\AutoFflCheckoutMultiShipping\Plugin\Multishipping\Controller\Checkout;

use Closure;
use Magento\Checkout\Controller\Index\Index as ParentControllor;
use Razoyo\AutoFflCheckoutMultiShipping\Helper\Data as MultishippingHelper;
use Magento\Framework\App\Action\Context;

/**
 * Class Addresses
 */
class Addresses
{
    /**
     * @var MultishippingHelper
     */
    private $multishippingHelper;

    /**
     * @var Context
     */
    private $context;

    /**
     * @param MultishippingHelper $multishippingHelper
     */
    public function __construct(
        MultishippingHelper $multishippingHelper,
        Context $context
    ) {
        $this->multishippingHelper = $multishippingHelper;
        $this->context = $context;
    }

    /**
     * Add a message telling the customer that he will be required to
     * if the feature is enabled and has a FFL item in the quote
     *
     * @param \Magento\Multishipping\Controller\Checkout\Addresses $subject
     * @param Closure $proceed
     * @return mixed
     */
    public function aroundExecute(\Magento\Multishipping\Controller\Checkout\Addresses $subject, Closure $proceed)
    {
        if ($this->multishippingHelper->hasFflItem()) {
            $this->context->getMessageManager()->addNoticeMessage(
                __('You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the shipping address(es).'));
        }

        return $proceed();
    }
}
