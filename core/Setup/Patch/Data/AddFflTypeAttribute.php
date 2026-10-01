<?php
namespace RefactoredGroup\AutoFflCore\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddFflTypeAttribute implements DataPatchInterface
{
    public function apply()
    {
        // Retain the patch name for installed upgrade histories without adding a new field.
        return $this;
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
