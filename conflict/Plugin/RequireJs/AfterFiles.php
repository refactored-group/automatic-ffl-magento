<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
 namespace RefactoredGroup\AutoFflConflict\Plugin\RequireJs;

class AfterFiles
{
    /**
     * @var Data
     */
    private $helper;

    public function __construct(
        \RefactoredGroup\AutoFflConflict\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * @return mixed
     */
    public function afterGetFiles(
        \Magento\Framework\RequireJs\Config\File\Collector\Aggregated $subject,
        $result
    ) :mixed {
        $extensions = $this->helper->getExtensions();
        if ($extensions !== null) {
            foreach ($result as $key => &$file) {
                /**
                 * Check if vendor module that has conflict with FFL is not installed or disabled.
                 * 
                 * If true, we will not load the requirejs config which contains the fix for the
                 * vendor module since there is no original vendor module to override.
                 */
                if (in_array($file->getModule(), $extensions)) {
                    unset($result[$key]);
                }
            }
        }
        return $result;
    }
}