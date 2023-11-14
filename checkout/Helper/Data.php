<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflCheckout\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var array
     */
    private $extensions = null;

    /**
     * @var ModuleList
     */
    private $moduleList;

    public function __construct(\Magento\Framework\Module\ModuleList $moduleList)
    {
        $this->moduleList = $moduleList;
    }

    /**
     * @return array|null
     */
    public function getExtensions(): ?array
    {
        if ($this->extensions === null) {
            $enabledModules = $this->getEnabledModules();
            $vendorExtensions = $this->mapVendorExtensions();
            foreach ($vendorExtensions as $key => $value) {
                // Check if vendor module is disabled or not installed
                if (!in_array($value, $enabledModules)) {
                    $this->extensions[] = $key;
                }
            }
        }
        return $this->extensions;
    }

    /**
     * @return array
     */
    private function getEnabledModules(): array
    {
        return $this->moduleList->getNames();
    }

    /**
     * @return array
     */
    private function mapVendorExtensions(): array
    {
        return [
            'RefactoredGroup_Vertex_AddressValidation' => 'Vertex_AddressValidation',
            'RefactoredGroup_Amasty_Xnotif' => 'Amasty_Xnotif',
        ];
    }
}