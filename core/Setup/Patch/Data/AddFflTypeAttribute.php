<?php
namespace RefactoredGroup\AutoFflCore\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use RefactoredGroup\AutoFflCore\Model\Product\Attribute\Source\FflType;

class AddFflTypeAttribute implements DataPatchInterface
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
        $eav = $this->eavSetupFactory->create(['setup' => $this->setup]);
        if (!$eav->getAttributeId(Product::ENTITY, 'ffl_type')) {
            $eav->addAttribute(Product::ENTITY, 'ffl_type', [
                'type' => 'varchar',
                'label' => 'FFL Type',
                'input' => 'select',
                'source' => FflType::class,
                'required' => false,
                'user_defined' => true,
                'visible' => true,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'default' => '',
                'sort_order' => 110
            ]);
        }
        $entityTypeId = $eav->getEntityTypeId(Product::ENTITY);
        foreach ($eav->getAllAttributeSetIds($entityTypeId) as $setId) {
            $eav->addAttributeGroup($entityTypeId, $setId, 'Automatic FFL', 200);
            $eav->addAttributeToGroup($entityTypeId, $setId, 'Automatic FFL', 'ffl_type', 10);
        }
        $this->setup->getConnection()->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
