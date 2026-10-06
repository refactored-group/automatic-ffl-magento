<?php
namespace RefactoredGroup\AutoFflCore\Block;

use Magento\Framework\View\Element\Template;
use Magento\Directory\Helper\Data as Directory;
use RefactoredGroup\AutoFflCore\Helper\Data;

class RoutingState extends Template
{
    private $helper;
    private $directoryHelper;

    public function __construct(Template\Context $context, Data $helper, Directory $directory, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->directoryHelper = $directory;
    }

    public function shouldRender()
    {
        return $this->helper->getCheckoutRoute() === 'state' ||
            ($this->getNameInLayout() === 'autoffl.destination' &&
                $this->helper->getCheckoutEntryRoute() === 'state');
    }

    public function getComponentConfig()
    {
        return json_encode(['Magento_Ui/js/core/app' => ['components' => ['autofflDestination' => [
            'component' => 'RefactoredGroup_AutoFflCore/js/checkout/routing-state',
            'isVisible' => true,
            'beforeCheckout' => true,
            'selectedState' => $this->helper->getRoutingState(),
            'routingStateUrl' => $this->helper->getRoutingStateUrl(),
            'formKey' => $this->helper->getFormKey(),
            'regionJson' => $this->directoryHelper->getRegionJson()
        ]]]]);
    }
}
