<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Observer;

use RefactoredGroup\AutoFflAdmin\Model\Api\ExtensionsProvider;
use RefactoredGroup\AutoFflAdmin\Model\ModuleInfoProvider;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\AbstractBlock;

class AutomaticFflInformationTab implements ObserverInterface
{
    public const TAB_NAME = 'Automatic FFL';
    public const MODULE_CODE ='RefactoredGroup_AutoFflMagento';
    public const FFL_REPOSITORY_URN = 'https://github.com/refactored-group/automatic-ffl-magento/releases';

    /**
     * @var AbstractBlock
     */
    private $block;

    /**
     * @var ExtensionsProvider
     */
    private $extensionsProvider;

    /**
     * @var ModuleInfoProvider
     */
    private $moduleInfoProvider;

    public function __construct(
        ExtensionsProvider $extensionsProvider,
        ModuleInfoProvider $moduleInfoProvider
    ) {
        $this->extensionsProvider = $extensionsProvider;
        $this->moduleInfoProvider = $moduleInfoProvider;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $block = $observer->getBlock();
        if ($block) {
            $this->setBlock($block);
            $html = $this->generateHtml();
            $block->setContent($html);
        }
    }

    /**
     * @return mixed
     */
    public function getBlock()
    {
        return $this->block;
    }

    /**
     * @param mixed $block
     */
    public function setBlock($block)
    {
        $this->block = $block;
    }

    /**
     * @return string|null
     */
    protected function getCurrentVersion()
    {
        $data = $this->moduleInfoProvider->getModuleInfo($this->getModuleCode());

        return isset($data['version']) ? $data['version'] : null;
    }

    /**
     * @return string|null
     */
    protected function getRepositoryVersion()
    {
        $repository = $this->extensionsProvider->getApiModuleData();

        return isset($repository['name']) ? $repository['name'] : null;
    }

    /**
     * @return string
     */
    protected function getTabName(): string
    {
        return self::TAB_NAME;
    }

    /**
     * @return string
     */
    protected function getModuleCode(): string
    {
        return self::MODULE_CODE;
    }

    /**
     * @return string
     */
    private function generateHtml()
    {
        $html = '<div class="refactored-group-info-block">'
            . $this->showVersionInfo();
        $html .= '</div>';

        return $html;
    }

    /**
     * @param string $currentVersion
     * @param string $repositoryVersion
     * 
     * @return int
     */
    private function matchVersionNumbers($currentVersion, $repositoryVersion)
    {
        return version_compare($currentVersion, $repositoryVersion);
    }

    /**
     * @param string $currentVersion
     * @param string $repositoryVersion
     * 
     * @return string
     */
    private function getVersionClassName($currentVersion, $repositoryVersion)
    {
        if ($currentVersion !== null &&
            $repositoryVersion === null) {
            return 'dev-version';
        }

        switch ($this->matchVersionNumbers(
            $currentVersion,
            $repositoryVersion
        )) {
            case -1:
                $cssClassName = 'last-version';
                break;
            case 1:
                $cssClassName = 'dev-version';
                break;
            default:
                $cssClassName = 'match-version';
                break;
        }
        return $cssClassName;
    }

    /**
     * @param string $repositoryVersion
     * 
     * @return string
     */
    private function getRepositoryUrl($repositoryVersion)
    {
        return '<a href="'
            . self::FFL_REPOSITORY_URN
            .'" target="_blank"'
            . ' class="match-version">'
            . $repositoryVersion
            . '</a>';
    }


    /**
     * @param string $currentVersion
     * @param string $repositoryVersion
     * 
     * @return string
     */
    private function showBannerInfo($currentVersion, $repositoryVersion)
    {
        $showBanner = false;
        $html = '';
        $content = '';

        if ($currentVersion === null) {
            $showBanner = true;
            $content = __('Unable to retrieve version number.');
        } elseif ($repositoryVersion === null) {
            $showBanner = true;
            $content = __('Unable to retrieve latest version number.');
        } else {
            switch ($this->matchVersionNumbers($currentVersion, $repositoryVersion)) {
                case -1:
                    $showBanner = true;
                    $content = __(
                        sprintf(
                            'An update to a version %s is available.',
                            $this->getRepositoryUrl($repositoryVersion)
                        )
                    );
                    break;
                case 1:
                    $showBanner = true;
                    $content = __('You are on a development version.');
                    break;
            }
        }

        if ($showBanner) {
            $html .= '<div><span class="upgrade-error message message-warning">'
                . $content
                . '</span>'
                . '</div>';
        }
        return $html;
    }

    /**
     * @return string
     */
    private function showVersionInfo()
    {
        $html = '<div class="refactored-group-module-version">';
        $currentVersion = $this->getCurrentVersion();
        $repositoryVersion = $this->getRepositoryVersion();

        $html .= '<div><span class="version-title">'
            . $this->getTabName() . ' '
            . '<span class="module-version '
            . $this->getVersionClassName($currentVersion, $repositoryVersion)
            . '">' . $currentVersion . '</span>'
            . '</span></div>'
            . $this->showBannerInfo($currentVersion, $repositoryVersion);

        $html .= '</div>';

        return $html;
    }
}