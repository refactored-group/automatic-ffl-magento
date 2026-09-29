<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\ScopeInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class RestrictionsClient
{
    private $scopeConfig;
    private $curlFactory;

    public function __construct(ScopeConfigInterface $scopeConfig, CurlFactory $curlFactory)
    {
        $this->scopeConfig = $scopeConfig;
        $this->curlFactory = $curlFactory;
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
        $client = $this->curlFactory->create();
        $client->setTimeout(5);
        $client->setOption(CURLOPT_FOLLOWLOCATION, false);
        $client->addHeader('Accept', 'application/json');
        try {
            if ($body === null) {
                $client->get($url);
            } else {
                $client->addHeader('Content-Type', 'application/json');
                $client->post($url, json_encode($body));
            }
            if ($client->getStatus() !== 200) {
                throw new \RuntimeException('Restrictions API status ' . $client->getStatus());
            }
            $data = json_decode((string) $client->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid restrictions API response');
            }
            return $data;
        } catch (\Exception $e) {
            throw new LocalizedException(__('FFL shipping rules could not be verified. Please try again.'));
        }
    }
}
