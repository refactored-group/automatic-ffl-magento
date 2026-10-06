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
     * Add dealer routing while retaining native customer address data.
     * @param \Magento\Checkout\Model\DefaultConfigProvider $subject
     * @param $result
     * @return array
     */
    public function afterGetConfig(\Magento\Checkout\Model\DefaultConfigProvider $subject, $result)
    {
        // Keep native address-book data available when ammunition switches back
        // to home delivery. The reactive address list hides it for dealer delivery.
        $result['customerData']['is_ffl'] = (int) $this->helper->isFfl();
        $result['autofflRouting'] = $this->helper->getCheckoutRoutingConfig();

        return $result;
    }
}
