<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\DataObject;

/**
 * Public order metadata derived from the saved dealer snapshot.
 */
class OrderFflData
{
    public function fromOrder(DataObject $order)
    {
        $snapshot = json_decode((string) $order->getData('ffl_dealer_data'), true);
        return $this->fromSnapshot(is_array($snapshot) ? $snapshot : [], $order->getData('ffl_license'));
    }

    public function fromSnapshot(array $snapshot, $fallbackLicense = null)
    {
        $license = $snapshot['license'] ?? $fallbackLicense;
        if (!is_string($license) || !preg_match('/^[A-Za-z0-9-]{1,32}$/', trim($license))) {
            $license = $fallbackLicense;
        }
        $license = is_string($license) && preg_match('/^[A-Za-z0-9-]{1,32}$/', trim($license))
            ? trim($license) : null;
        $data = [
            'license' => $license,
            'dealer_id' => null,
            'expiration_date' => null,
            'certificate_url' => null,
            'ezcheck_url' => null
        ];
        if ($license === null) {
            return $data;
        }

        $rawId = $snapshot['id'] ?? null;
        $id = (is_int($rawId) || is_string($rawId)) && preg_match('/^[1-9][0-9]*$/D', (string) $rawId)
            ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $data['dealer_id'] = $id === false ? null : $id;
        $date = $snapshot['expirationDate'] ?? null;
        $parsedDate = is_string($date) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if ($parsedDate && $parsedDate->format('Y-m-d') === $date) {
            $data['expiration_date'] = $date;
        }
        $uuid = $snapshot['uuid'] ?? null;
        if (is_string($uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            $data['certificate_url'] = 'https://certificate.automaticffl.com/' . rawurlencode($uuid);
        }
        $parts = explode('-', $license);
        $data['ezcheck_url'] = count($parts) === 6
            ? 'https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=' . rawurlencode($parts[0]) .
                '&licsDis=' . rawurlencode($parts[1]) . '&licsSeq=' . rawurlencode($parts[5])
            : 'https://fflezcheck.atf.gov/FFLEzCheck/';
        return $data;
    }

    public function formatComment(array $data)
    {
        // Keep BigCommerce's labels, separators, and MM/DD/YYYY date representation.
        $expiration = $data['expiration_date'] === null ? ''
            : \DateTimeImmutable::createFromFormat('!Y-m-d', $data['expiration_date'])->format('m/d/Y');
        $comment = 'FFL#' . $data['license'] . '|Expiration:' . $expiration . '|EZcheck:' . $data['ezcheck_url'];
        if ($data['certificate_url'] !== null) {
            $comment .= '|Certificate:' . $data['certificate_url'];
        }
        return $comment;
    }
}
