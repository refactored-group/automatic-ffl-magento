<?php
/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace Razoyo\AutoFflCore\Plugin;

class AddressCollectionFactoryPlugin
{
    function afterCreate(\Magento\Customer\Model\ResourceModel\Address\CollectionFactory $subject, $collection)
    {
        $collection->addAttributeToFilter([
            ['attribute' => 'is_deleted', 'null' => true],
            ['attribute' => 'is_deleted', 'neq' => 1]
        ]);

        return $collection;
    }
}
