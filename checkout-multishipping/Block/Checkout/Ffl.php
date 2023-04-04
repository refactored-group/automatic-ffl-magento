<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckoutMultiShipping\Block\Checkout;

use Magento\Framework\View\Element\Template;
use RefactoredGroup\AutoFflCore\Helper\Data;

class Ffl extends Template
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @param Template\Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * Returns a bool of whether this quote has a FFL item or not
     * @return bool|null
     */
    public function isFfl()
    {
        return $this->helper->hasFflItem();
    }
}
