<?php
namespace RefactoredGroup\AutoFflCore\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class HideFflTypeAttribute implements DataPatchInterface
{
    private $setup;
    private $eavSetupFactory;

    public function __construct(ModuleDataSetupInterface $setup, EavSetupFactory $eavSetupFactory)
    {
        $this->setup = $setup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        $this->setup->getConnection()->startSetup();
        try {
            $eav = $this->eavSetupFactory->create(['setup' => $this->setup]);
            if ($eav->getAttributeId(Product::ENTITY, 'ffl_type')) {
                // Keep any saved values and their source model for upgrade compatibility.
                $eav->updateAttribute(Product::ENTITY, 'ffl_type', 'is_visible', 0);
            }
        } finally {
            $this->setup->getConnection()->endSetup();
        }
        return $this;
    }

    public static function getDependencies()
    {
        return [AddFflTypeAttribute::class];
    }

    public function getAliases()
    {
        return [];
    }
}
