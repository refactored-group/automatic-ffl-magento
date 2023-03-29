<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCore\Plugin;

class AddressCollectionFactoryPlugin
{
    public function afterCreate(\Magento\Customer\Model\ResourceModel\Address\CollectionFactory $subject, $collection)
    {
        $collection->addAttributeToFilter([
            ['attribute' => 'is_deleted', 'null' => true],
            ['attribute' => 'is_deleted', 'neq' => 1]
        ]);

        return $collection;
    }
}
