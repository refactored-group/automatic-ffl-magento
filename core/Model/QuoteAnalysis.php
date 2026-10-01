<?php
namespace RefactoredGroup\AutoFflCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class QuoteAnalysis
{
    private $client;
    private $scopeConfig;
    private $cache = [];

    public function __construct(RestrictionsClient $client, ScopeConfigInterface $scopeConfig)
    {
        $this->client = $client;
        $this->scopeConfig = $scopeConfig;
    }

    public function analyze(Quote $quote, $destinationState = null)
    {
        if ($destinationState === null) {
            $destinationState = $quote->getFflRoutingState();
        }
        $storeId = (int) $quote->getStoreId();
        $descriptors = [];
        $items = [];
        $physicalItems = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getProduct()->isVirtual()) {
                continue;
            }
            $physicalItems[] = $item;
            $descriptor = $this->descriptor($item);
            $descriptors[] = $descriptor;
            $items[(string) $descriptor['id']][] = $item;
        }
        if (!$descriptors) {
            return ['required' => [], 'firearms' => [], 'ammo' => [], 'unresolved' => false,
                'allRequired' => false, 'hasAmmunition' => false, 'state' => ''];
        }

        $sandbox = (bool) $this->scopeConfig->getValue(
            Data::XML_PATH_SANDBOX_MODE, ScopeInterface::SCOPE_STORE, $storeId
        );
        $hash = (string) $this->scopeConfig->getValue(
            Data::XML_PATH_STORE_HASH, ScopeInterface::SCOPE_STORE, $storeId
        );
        $state = strtoupper(trim((string) $destinationState));
        $key = hash('sha256', json_encode([$storeId, $sandbox, $hash, $state, $descriptors]));
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $policy = $this->client->getPolicy($storeId);
        $categoryRules = array_merge(
            (array) ($policy['firearm_category_ids'] ?? []),
            (array) ($policy['ammo_category_ids'] ?? [])
        );
        foreach ($descriptors as $index => $descriptor) {
            if (array_intersect($descriptor['categoryIds'], $categoryRules)) {
                // The old flag is a fallback, including with older compatible backends.
                $descriptors[$index]['required_ffl'] = false;
            }
        }
        $restrictions = $this->client->getProducts($storeId, $descriptors);
        $firearms = [];
        $ammo = [];
        foreach ($restrictions as $restriction) {
            $id = (string) $restriction['id'];
            if (!isset($items[$id])) {
                continue;
            }
            if (empty($restriction['conditions'])) {
                $firearms = array_merge($firearms, $items[$id]);
            } else {
                foreach ($restriction['conditions'] as $condition) {
                    if (($condition['type'] ?? null) === 'ship_state' && isset($condition['states']) &&
                        is_array($condition['states'])) {
                        $ammo[$id] = ['items' => $items[$id], 'states' => $condition['states']];
                    }
                }
            }
        }

        $shipAll = (bool) $this->scopeConfig->getValue(
            Data::XML_PATH_SHIP_NON_GUN_ITEMS, ScopeInterface::SCOPE_STORE, $storeId
        );
        $required = $firearms;
        $unresolved = false;
        foreach ($ammo as $entry) {
            $forceWithFirearms = $firearms && ($shipAll || empty($policy['apply_ammo_state_rules_in_mixed_carts']));
            if ($forceWithFirearms || in_array($state, $entry['states'], true)) {
                $required = array_merge($required, $entry['items']);
            } elseif ($state === '' && !empty($entry['states'])) {
                $unresolved = true;
            }
        }
        if ($firearms && $shipAll) {
            $required = $physicalItems;
        }
        return $this->cache[$key] = [
            'required' => $required,
            'firearms' => $firearms,
            'ammo' => $ammo,
            'unresolved' => $unresolved,
            'allRequired' => count($required) === count($physicalItems),
            'hasAmmunition' => (bool) array_filter($ammo, function ($entry) {
                return !empty($entry['states']);
            }),
            'state' => $state
        ];
    }

    private function descriptor($item)
    {
        $products = [$item->getProduct()];
        foreach ((array) $item->getChildren() as $child) {
            $products[] = $child->getProduct();
        }
        $categories = [];
        $forced = false;
        foreach ($products as $product) {
            $categories = array_merge($categories, (array) $product->getCategoryIds());
            $forced = $forced || (bool) $product->getRequiredFfl();
        }
        return [
            'id' => (string) $item->getId(),
            'productId' => (string) $item->getProductId(),
            'required_ffl' => $forced,
            'categoryIds' => array_values(array_unique(array_map('intval', $categories)))
        ];
    }
}
