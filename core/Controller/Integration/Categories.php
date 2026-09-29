<?php
namespace RefactoredGroup\AutoFflCore\Controller\Integration;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use RefactoredGroup\AutoFflCore\Helper\Data;

class Categories extends Action implements HttpGetActionInterface
{
    private $helper;
    private $storeManager;
    private $categories;
    private $categoryRepository;
    private $jsonFactory;

    public function __construct(
        Context $context,
        Data $helper,
        StoreManagerInterface $storeManager,
        CollectionFactory $categories,
        CategoryRepositoryInterface $categoryRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->categories = $categories;
        $this->categoryRepository = $categoryRepository;
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $this->getResponse()->setHeader('Pragma', 'no-cache', true);
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $expected = (string) $this->helper->getStoreSecret($storeId);
        $provided = (string) $this->getRequest()->getHeader('X-AutoFFL-Store-Secret');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return $result->setHttpResponseCode(401)->setData(['error' => 'Unauthorized']);
        }

        $hash = (string) $this->helper->getStoreHash($storeId);
        if ($hash === '') {
            return $result->setHttpResponseCode(422)->setData(['error' => 'AutoFFL store hash is not configured']);
        }
        $roots = [];
        foreach ($this->storeManager->getStores() as $candidate) {
            $candidateId = (int) $candidate->getId();
            if ($this->helper->getStoreHash($candidateId) === $hash &&
                hash_equals($expected, (string) $this->helper->getStoreSecret($candidateId))) {
                $rootId = (int) $candidate->getRootCategoryId();
                if ($rootId > 0 && !isset($roots[$rootId])) {
                    $roots[$rootId] = (string) $candidate->getName();
                }
            }
        }
        if (!$roots) {
            return $result->setHttpResponseCode(422)->setData(['error' => 'No Magento category root matches this AutoFFL connection']);
        }

        $tree = [];
        $count = 0;
        foreach ($roots as $rootId => $name) {
            $root = $this->categoryRepository->get($rootId, $storeId);
            $collection = $this->categories->create()
                ->setStoreId($storeId)
                ->addAttributeToSelect('name')
                ->addFieldToFilter('path', ['like' => $root->getPath() . '/%']);
            $entries = [];
            $seenIds = [];
            foreach ($collection as $category) {
                $id = (int) $category->getId();
                $parentId = (int) $category->getParentId();
                if ($id <= 0 || isset($seenIds[$id]) || ++$count > 5000) {
                    return $result->setHttpResponseCode(413)->setData(['error' => 'Category tree exceeds supported size']);
                }
                $seenIds[$id] = true;
                $entries[$parentId][] = [
                    'id' => $id,
                    'description' => (string) $category->getName(),
                    'children' => []
                ];
            }
            try {
                $visited = [];
                $children = $this->buildChildren((int) $rootId, $entries, 0, $visited);
                if (count($visited) !== count($seenIds)) {
                    throw new \RuntimeException('Category tree is incomplete');
                }
                $tree[] = [
                    'id' => (int) $rootId,
                    'description' => $name,
                    'children' => $children
                ];
            } catch (\RuntimeException $e) {
                return $result->setHttpResponseCode(413)->setData(['error' => 'Category tree is incomplete or too deep']);
            }
        }

        return $result->setData($tree);
    }

    private function buildChildren($parentId, array $entries, $depth, array &$visited)
    {
        $children = $entries[$parentId] ?? [];
        if ($depth >= 20 && $children) {
            throw new \RuntimeException('Category tree is too deep');
        }
        foreach ($children as &$child) {
            if (isset($visited[$child['id']])) {
                throw new \RuntimeException('Category tree contains a cycle or duplicate');
            }
            $visited[$child['id']] = true;
            $child['children'] = $this->buildChildren($child['id'], $entries, $depth + 1, $visited);
        }
        return $children;
    }
}
