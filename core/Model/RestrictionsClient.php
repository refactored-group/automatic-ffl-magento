<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class RestrictionsClient
{
    private const MAX_ATTEMPTS = 2;
    private const RETRY_DELAY_MICROSECONDS = 250000;

    private $scopeConfig;
    private $curlFactory;
    private $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CurlFactory $curlFactory,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
    }

    public function getPolicy($storeId)
    {
        $policy = $this->request($storeId, 'restrictions', null);
        if (!is_array($policy) || ($policy['magento_policy_supported'] ?? false) !== true ||
            !isset($policy['ammo_states']) || !is_array($policy['ammo_states'])) {
            throw new LocalizedException(__('FFL shipping rules are unavailable. Please try again.'));
        }
        return $policy;
    }

    public function getProducts($storeId, array $descriptors)
    {
        $products = $this->request($storeId, 'products/restrictions', ['products' => $descriptors]);
        if (!is_array($products) || array_values($products) !== $products) {
            throw new LocalizedException(__('FFL product rules are unavailable. Please try again.'));
        }
        foreach ($products as $product) {
            if (!is_array($product) || !isset($product['id']) || !is_string($product['id'])) {
                throw new LocalizedException(__('FFL product rules are unavailable. Please try again.'));
            }
        }
        return $products;
    }

    private function request($storeId, $path, $body)
    {
        $hash = (string) $this->scopeConfig->getValue(
            Data::XML_PATH_STORE_HASH, ScopeInterface::SCOPE_STORE, $storeId
        );
        $sandbox = (bool) $this->scopeConfig->getValue(
            Data::XML_PATH_SANDBOX_MODE, ScopeInterface::SCOPE_STORE, $storeId
        );
        if ($hash === '') {
            throw new LocalizedException(__('The AutoFFL store hash is not configured.'));
        }
        $base = $sandbox ? Data::API_SANDBOX_URL : Data::API_PRODUCTION_URL;
        $url = $base . '/stores/' . rawurlencode($hash) . '/' . $path;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $client = $this->curlFactory->create();
            $client->setTimeout(5);
            $client->setOption(CURLOPT_CONNECTTIMEOUT, 2);
            $client->setOption(CURLOPT_FOLLOWLOCATION, false);
            $client->addHeader('Accept', 'application/json');
            $status = null;
            $reason = 'transport_failure';
            $exceptionType = null;
            $retryable = true;

            try {
                if ($body === null) {
                    $client->get($url);
                } else {
                    $client->addHeader('Content-Type', 'application/json');
                    $client->post($url, json_encode($body));
                }
                $status = (int) $client->getStatus();
                if ($status === 200) {
                    $data = json_decode((string) $client->getBody(), true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $data;
                    }
                    $reason = 'invalid_response';
                } else {
                    $reason = 'http_status';
                    $retryable = $status === 429 || $status >= 500;
                }
            } catch (\Throwable $e) {
                $exceptionType = get_class($e);
            }

            $this->logger->warning('AutoFFL shipping rules request failed', [
                'path' => $path,
                'status' => $status,
                'attempt' => $attempt,
                'reason' => $reason,
                'exception' => $exceptionType
            ]);
            if (!$retryable || $attempt >= self::MAX_ATTEMPTS) {
                break;
            }
            usleep(self::RETRY_DELAY_MICROSECONDS);
        }

        throw new LocalizedException(__('FFL shipping rules could not be verified. Please try again.'));
    }
}
