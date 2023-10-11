<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Model;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\SerializerInterface;

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
        Reader $moduleReader,
        File $filesystem,
        SerializerInterface $serializer,
    ) {
        $this->moduleReader = $moduleReader;
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
    }

    public function getModuleInfo(string $moduleCode)
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