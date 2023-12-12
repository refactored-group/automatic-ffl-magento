<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
 namespace RefactoredGroup\AmastyXnotif\Plugin\RequireJs;

class AfterFiles
{
    private const MODULE_NAME = 'RefactoredGroup_Amasty_Xnotif';
    private const VENDOR_MODULE_NAME = 'Amasty_Xnotif';

    /**
     * @var ModuleList
     */
    private $moduleList;

    public function __construct(\Magento\Framework\Module\ModuleList $moduleList)
    {
        $this->moduleList = $moduleList;
    }

    /**
     * @return mixed
     */
    public function afterGetFiles(
        \Magento\Framework\RequireJs\Config\File\Collector\Aggregated $subject,
        $result
    ) :mixed {
        if ($this->isVendorModuleDisabled()) {
            foreach ($result as $key => &$file) {
                /**
                 * Check if vendor module that has conflict with FFL is not installed or disabled.
                 * 
                 * If true, we will not load the requirejs config which contains the fix for the
                 * vendor module since there is no original vendor module to override.
                 */
                if ($file->getModule() === self::MODULE_NAME) {
                    unset($result[$key]);
                }
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    private function isVendorModuleDisabled(): bool
    {
        $enabledModules = $this->getEnabledModules();
        return !in_array(
            self::VENDOR_MODULE_NAME,
            $enabledModules
        );
    }

    /**
     * @return array
     */
    private function getEnabledModules(): array
    {
        return $this->moduleList->getNames();
    }
}