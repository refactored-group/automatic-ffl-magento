<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Sales\Model\Order;

class OrderMetadata
{
    public function apply(Order $order, array $snapshot)
    {
        if ($order->getFflDealerData()) {
            return;
        }
        $order->setFflLicense($snapshot['license']);
        $order->setFflDealerData(json_encode($snapshot));

        $parts = explode('-', $snapshot['license']);
        $ezCheck = count($parts) === 6
            ? 'https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=' . rawurlencode($parts[0]) .
                '&licsDis=' . rawurlencode($parts[1]) . '&licsSeq=' . rawurlencode($parts[5])
            : 'https://fflezcheck.atf.gov/FFLEzCheck/';
        $comment = 'FFL#' . $snapshot['license'] . "\n" .
            'Expiration: ' . ($snapshot['expirationDate'] ?: 'unavailable — verify with eZ Check') . "\n" .
            'eZ Check: ' . $ezCheck;
        if (!empty($snapshot['uuid'])) {
            $comment .= "\nCertificate: https://certificate.automaticffl.com/" . rawurlencode($snapshot['uuid']);
        }
        $history = $order->addStatusHistoryComment($comment, false);
        $history->setIsVisibleOnFront(false);
        $history->setIsCustomerNotified(false);
    }
}
