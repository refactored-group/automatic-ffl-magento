<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\Exception\LocalizedException;
use RefactoredGroup\AutoFflCore\Helper\Data;

class DealerSnapshot
{
    private $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    public function fromJson($json, $license, $storeId)
    {
        if (!is_string($json) || strlen($json) > 8192) {
            throw new LocalizedException(__('The dealer selection is invalid. Please select a dealer again.'));
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['id']) || !preg_match('/^[1-9][0-9]*$/', (string) $data['id']) ||
            !isset($data['license']) || !is_string($data['license']) ||
            strlen($data['license']) > 32 || !preg_match('/^[A-Za-z0-9-]+$/', trim($data['license'])) ||
            strtoupper(trim($data['license'])) !== strtoupper(trim((string) $license)) ||
            ($data['countryCode'] ?? null) !== 'US') {
            throw new LocalizedException(__('The dealer selection does not match the shipping address.'));
        }
        foreach (['address1', 'city', 'state', 'postalCode'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new LocalizedException(__('The dealer address is incomplete. Please select a dealer again.'));
            }
        }
        if (!preg_match('/^[A-Z]{2}$/', $data['state'])) {
            throw new LocalizedException(__('The dealer state is invalid.'));
        }
        $routingState = $data['routingState'] ?? null;
        if ($routingState !== null && $routingState !== '' &&
            (!is_string($routingState) || !preg_match('/^[A-Z]{2}$/', $routingState))) {
            throw new LocalizedException(__('The original delivery state is invalid.'));
        }

        $date = $data['expirationDate'] ?? null;
        $parsedDate = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            $date = null;
        }
        $uuid = $data['uuid'] ?? null;
        if (!is_string($uuid) || !preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            $uuid = null;
        }

        return [
            'version' => 1,
            'id' => (int) $data['id'],
            'license' => trim($data['license']),
            'uuid' => $uuid,
            'expirationDate' => $date,
            'company' => $this->shortText($data['company'] ?? ''),
            'firstName' => $this->optionalShortText($data['firstName'] ?? null),
            'lastName' => $this->optionalShortText($data['lastName'] ?? null),
            'phone' => $this->shortText($data['phone'] ?? ''),
            'address1' => $this->shortText($data['address1']),
            'address2' => $this->shortText($data['address2'] ?? ''),
            'city' => $this->shortText($data['city']),
            'state' => $data['state'],
            'postalCode' => $this->shortText($data['postalCode']),
            'routingState' => $routingState ?: null,
            'storeHash' => (string) $this->helper->getStoreHash($storeId),
            'sandbox' => (bool) $this->helper->isSandboxMode($storeId)
        ];
    }

    private function shortText($value)
    {
        if (!is_string($value) || mb_strlen($value) > 255) {
            throw new LocalizedException(__('The dealer selection is too long. Please select a dealer again.'));
        }
        return trim($value);
    }

    private function optionalShortText($value)
    {
        return $value === null || $value === '' ? null : $this->shortText($value);
    }
}
