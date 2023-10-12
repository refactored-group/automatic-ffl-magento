<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Information extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var AutomaticFflInformationTab
     */
    private $fflInformationTab;

    public function __construct(
        \RefactoredGroup\AutoFflAdmin\Observer\AutomaticFflInformationTab $fflInformationTab,
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);
        $this->fflInformationTab = $fflInformationTab;
    }

    /**
     * @param AbstractElement $element
     * 
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $html = $this->_getHeaderHtml($element);
        $this->setContent(
            $this->fflInformationTab->generateHtml()
        );
        $html .= $this->getContent();
        $html .= $this->_getFooterHtml($element);
        $html = str_replace(
            'refactored_group_information]" type="hidden" value="0"',
            'refactored_group_information]" type="hidden" value="1"',
            $html
        );
        return $html;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content): void
    {
        $this->content = $content;
    }
}