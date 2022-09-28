<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

namespace RefactoredGroup\AutoFflCheckout\Plugin\Checkout\Model;

use Closure;
use RefactoredGroup\AutoFflCore\Helper\Data as Helper;
use Magento\Framework\App\Action\Context;

class DefaultConfigProvider
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
     * Remove all customer addresses from the config provider when checking out with FFL
     * @param \Magento\Checkout\Model\DefaultConfigProvider $subject
     * @param $result
     * @return array
     */
    public function afterGetConfig(\Magento\Checkout\Model\DefaultConfigProvider $subject, $result)
    {
        /**
         * If FFL is enabled and all items in the cart are FFL, we can not display
         * any of the Customer Address Book addresses during the checkout.
         */
        if ($this->helper->isFflCart()) {
            $result['customerData']['billingAddresses'] = !empty($result['customerData']['addresses']) ?: [] ;
            $result['customerData']['addresses'] = [];
        }
        $result['customerData']['is_ffl'] = (int) $this->helper->isFflCart();

        return $result;
    }
}
