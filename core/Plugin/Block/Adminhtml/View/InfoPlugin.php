<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Plugin\Block\Adminhtml\View;

use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Magento\Sales\Model\Order\Address;
use Magento\Framework\Escaper;

class InfoPlugin
{
    private $escaper;

    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    public function afterGetFormattedAddress(Info $subject, String $html, Address $address) : String
    {
        $addressType = $address->getAddressType();
        if($addressType == 'shipping') {
            $order = $address->getOrder();
            $snapshot = json_decode((string) $order->getFflDealerData(), true);
            $fflLicense = is_array($snapshot) && !empty($snapshot['license'])
                ? $snapshot['license'] : $order->getFflLicense();
            if ($fflLicense) {
                $html .= '<br/>FFL License: ' . $this->escaper->escapeHtml($fflLicense);
                $expiration = is_array($snapshot) ? ($snapshot['expirationDate'] ?? null) : null;
                $html .= '<br/>Expiration: ' . $this->escaper->escapeHtml(
                    $expiration ?: 'unavailable — verify with eZ Check'
                );
                $parts = explode('-', $fflLicense);
                if (count($parts) === 6) {
                    $ezCheck = 'https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=' . rawurlencode($parts[0]) .
                        '&licsDis=' . rawurlencode($parts[1]) . '&licsSeq=' . rawurlencode($parts[5]);
                    $html .= '<br/><a href="' . $this->escaper->escapeUrl($ezCheck) . '" target="_blank" rel="noopener">eZ Check</a>';
                }
                if (is_array($snapshot) && !empty($snapshot['uuid']) &&
                    preg_match('/^[0-9a-f-]{36}$/i', $snapshot['uuid'])) {
                    $certificate = 'https://certificate.automaticffl.com/' . rawurlencode($snapshot['uuid']);
                    $html .= '<br/><a href="' . $this->escaper->escapeUrl($certificate) . '" target="_blank" rel="noopener">Certificate</a>';
                }
            }
        }
        return $html;
    }
}
