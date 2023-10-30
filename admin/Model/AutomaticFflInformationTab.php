<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Model;

class AutomaticFflInformationTab
{
    public const TAB_NAME = 'Automatic FFL';
    public const MODULE_CODE ='RefactoredGroup_AutoFflMagento';
    public const FFL_REPOSITORY_URN = 'https://github.com/refactored-group/automatic-ffl-magento/releases';

    public const SAME_VERSION_CSS_LABEL = 'same-version';
    public const DEVELOPER_VERSION_CSS_LABEL = 'developer-version';
    public const LOWER_VERSION_CSS_LABEL = 'lower-version';

    public const CURRENT_IS_LOWER_VERSION = 5;
    public const CURRENT_IS_SAME_WITH_RELEASE_VERSION = 10;
    public const CURRENT_IS_DEVELOPER_VERSION = 15;
    public const RELEASE_IS_DEVELOPER_VERSION = 20;
    public const UNABLE_TO_FETCH_CURRENT_VERSION = 40;
    public const UNABLE_TO_FETCH_RELEASE_VERSION = 50;

    private const RELEASE_DEVELOPER_SUFFIX = '-dev';

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
        ModuleInfoProvider $moduleInfoProvider
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
    protected function getCurrentVersion(): ?string
    {
        $data = $this->moduleInfoProvider->getModuleInfo($this->getModuleCode());

        return isset($data['version']) ? $data['version'] : null;
    }

    /**
     * @return string|null
     */
    protected function getRepositoryVersion(): ?string
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
     * @param string|null $currentVersion
     * @param string|null $repositoryVersion
     * 
     * @return int|bool
     */
    private function matchVersionNumbers(string|null $currentVersion, string|null $repositoryVersion): int|bool
    {
        if (!$currentVersion) {
            return self::UNABLE_TO_FETCH_CURRENT_VERSION;
        } else if (!$repositoryVersion) {
            return self::UNABLE_TO_FETCH_RELEASE_VERSION;
        } else if (str_ends_with($currentVersion, self::RELEASE_DEVELOPER_SUFFIX)) {
            return self::CURRENT_IS_DEVELOPER_VERSION;
        } else if (str_ends_with($repositoryVersion, self::RELEASE_DEVELOPER_SUFFIX)) {
            return self::RELEASE_IS_DEVELOPER_VERSION;
        } else {
            switch (version_compare(
                $currentVersion,
                $repositoryVersion
            )) {
                case -1:
                    return self::CURRENT_IS_LOWER_VERSION;
                case 1:
                    return self::CURRENT_IS_DEVELOPER_VERSION;
                default:
                    return self::CURRENT_IS_SAME_WITH_RELEASE_VERSION;
            }
        }
    }

    /**
     * @param string|null $currentVersion
     * @param string|null $repositoryVersion
     * 
     * @return string
     */
    private function getVersionClassName(string|null $currentVersion, string|null $repositoryVersion)
    {
        switch ($this->matchVersionNumbers(
            $currentVersion,
            $repositoryVersion
        )) {
            case self::CURRENT_IS_LOWER_VERSION:
                $cssClassName = self::LOWER_VERSION_CSS_LABEL;
                break;
            case self::CURRENT_IS_DEVELOPER_VERSION:
            case self::RELEASE_IS_DEVELOPER_VERSION:
            case self::UNABLE_TO_FETCH_CURRENT_VERSION:
            case self::UNABLE_TO_FETCH_RELEASE_VERSION:
                $cssClassName = self::DEVELOPER_VERSION_CSS_LABEL;
                break;
            default:
                $cssClassName = self::SAME_VERSION_CSS_LABEL;
                break;
        }
        return $cssClassName;
    }

    /**
     * @param string $repositoryVersion
     * 
     * @return string
     */
    private function getRepositoryUrl(string $repositoryVersion): string
    {
        return '<a href="'
            . self::FFL_REPOSITORY_URN
            .'" target="_blank"'
            . ' class="' . self::SAME_VERSION_CSS_LABEL . '">'
            . $repositoryVersion
            . '</a>';
    }


    /**
     * @param string|null $currentVersion
     * @param string|null $repositoryVersion
     * 
     * @return string
     */
    private function showBannerInfo(string|null $currentVersion, string|null $repositoryVersion): string
    {
        $showBanner = false;
        $html = '';
        $content = '';

        switch ($this->matchVersionNumbers($currentVersion, $repositoryVersion)) {
            case self::CURRENT_IS_LOWER_VERSION:
                $showBanner = true;
                $content = __(
                    sprintf(
                        'An update to a version %s is available.',
                        $this->getRepositoryUrl($repositoryVersion)
                    )
                );
                break;
            case self::CURRENT_IS_DEVELOPER_VERSION:
                $showBanner = true;
                $content = __('You are on a development version.');
                break;
            case self::RELEASE_IS_DEVELOPER_VERSION:
                $showBanner = true;
                $content = __('Unable to retrieve latest version number.');
                break;
            case self::UNABLE_TO_FETCH_CURRENT_VERSION:
                $showBanner = true;
                $content = __('Unable to retrieve version number.');
                break;
            case self::UNABLE_TO_FETCH_RELEASE_VERSION:
                $showBanner = true;
                $content = __('Unable to retrieve latest version number.');
                break;
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
    private function showVersionInfo(): string
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