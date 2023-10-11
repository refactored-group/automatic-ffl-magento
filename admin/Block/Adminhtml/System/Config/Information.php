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
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = $this->_getHeaderHtml($element);
        $this->setContent(__('Automatic FFL'));
        $this->_eventManager->dispatch(
            'refactored_group_add_information_content',
            ['block' => $this]
        );
        $html .= $this->getContent();
        $html .= $this->_getFooterHtml($element);
        $html = str_replace(
            'refactored_group_information]" type="hidden" value="0"',
            'refactored_group_information]" type="hidden" value="1"',
            $html
        );
        $html = preg_replace('(onclick=\"Fieldset.toggleCollapse.*?\")', '', $html);
        return $html;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }
}