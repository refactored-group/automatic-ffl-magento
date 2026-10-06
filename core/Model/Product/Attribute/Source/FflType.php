<?php
namespace RefactoredGroup\AutoFflCore\Model\Product\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class FflType extends AbstractSource
{
    public function getAllOptions()
    {
        return [
            ['value' => '', 'label' => __('Use category rules')],
            ['value' => 'firearm', 'label' => __('Firearm')],
            ['value' => 'ammo', 'label' => __('Ammunition')]
        ];
    }
}
