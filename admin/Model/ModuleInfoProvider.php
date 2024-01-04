<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Model;

use Magento\Framework\Exception\FileSystemException;

class ModuleInfoProvider
{
    /**
     * @var string[]
     */
    protected $moduleDataStorage = [];

    /**
     * @var Reader
     */
    private $moduleReader;

    /**
     * @var File
     */
    private $filesystem;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Framework\Filesystem\Driver\File $filesystem,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    ) {
        $this->moduleReader = $moduleReader;
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
    }

    /**
     * @param string $moduleCode
     * 
     * @return array
     */
    public function getModuleInfo(string $moduleCode): array
    {
        if (!isset($this->moduleDataStorage[$moduleCode])) {
            $this->moduleDataStorage[$moduleCode] = [];

            try {
                $dir = $this->moduleReader->getModuleDir('', $moduleCode);
                $file = $dir . '/composer.json';

                $string = $this->filesystem->fileGetContents($file);
                $this->moduleDataStorage[$moduleCode] = $this->serializer->unserialize($string);
            } catch (FileSystemException $e) {
                $this->moduleDataStorage[$moduleCode] = [];
            }
        }

        return $this->moduleDataStorage[$moduleCode];
    }
}
