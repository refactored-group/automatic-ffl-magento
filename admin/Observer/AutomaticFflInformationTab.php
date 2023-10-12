<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Observer;

class AutomaticFflInformationTab
{
    public const TAB_NAME = 'Automatic FFL';
    public const MODULE_CODE ='RefactoredGroup_AutoFflMagento';
    public const FFL_REPOSITORY_URN = 'https://github.com/refactored-group/automatic-ffl-magento/releases';

    public const MATCH_CSS_LABEL = 'match-version';
    public const DEV_CSS_LABEL = 'dev-version';
    public const LAST_CSS_LABEL = 'last-version';

    /**
     * @var ExtensionsProvider
     */
    private $extensionsProvider;

    /**
     * @var ModuleInfoProvider
     */
    private $moduleInfoProvider;

    public function __construct(
        \RefactoredGroup\AutoFflAdmin\Model\Api\ExtensionsProvider $extensionsProvider,
        \RefactoredGroup\AutoFflAdmin\Model\ModuleInfoProvider $moduleInfoProvider
    ) {
        $this->extensionsProvider = $extensionsProvider;
        $this->moduleInfoProvider = $moduleInfoProvider;
    }

    /**
     * @return string
     */
    public function generateHtml(): string
    {
        $html = '<div class="refactored-group-info-block">'
            . $this->showVersionInfo();
        $html .= '</div>';

        return $html;
    }

    /**
     * @return string|null
     */
    protected function getCurrentVersion(): string|null
    {
        $data = $this->moduleInfoProvider->getModuleInfo($this->getModuleCode());

        return isset($data['version']) ? $data['version'] : null;
    }

    /**
     * @return string|null
     */
    protected function getRepositoryVersion(): string|null
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
     * @param string $currentVersion
     * @param string $repositoryVersion
     * 
     * @return int|bool
     */
    private function matchVersionNumbers($currentVersion, $repositoryVersion): int|bool
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
            return self::DEV_CSS_LABEL;
        }

        switch ($this->matchVersionNumbers(
            $currentVersion,
            $repositoryVersion
        )) {
            case -1:
                $cssClassName = self::LAST_CSS_LABEL;
                break;
            case 1:
                $cssClassName = self::DEV_CSS_LABEL;
                break;
            default:
                $cssClassName = self::MATCH_CSS_LABEL;
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
            . ' class="' . self::MATCH_CSS_LABEL . '">'
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
            $html .= '<div><span class="ffl-version-warning message message-warning">'
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