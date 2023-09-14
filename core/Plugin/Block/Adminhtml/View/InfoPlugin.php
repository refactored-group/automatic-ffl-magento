<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Plugin\Block\Adminhtml\View;

use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Magento\Sales\Model\Order\Address;

class InfoPlugin
{
    public function afterGetFormattedAddress(Info $subject, String $html, Address $address) : String
    {
        $addressType = $address->getAddressType();
        if($addressType == 'shipping') {
            $order = $address->getOrder();
            $fflLicense = $order->getFflLicense();
            if(!is_null($fflLicense)) {
                $html = $html.'<br/> FFL License: '.$fflLicense;
            }
        }
        return $html;
    }
}
