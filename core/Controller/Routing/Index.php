<?php
namespace RefactoredGroup\AutoFflCore\Controller\Routing;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use RefactoredGroup\AutoFflCore\Helper\Data;

class Index extends Action implements HttpGetActionInterface
{
    private $pages;
    private $helper;

    public function __construct(Context $context, PageFactory $pages, Data $helper)
    {
        parent::__construct($context);
        $this->pages = $pages;
        $this->helper = $helper;
    }

    public function execute()
    {
        if ($this->helper->getCheckoutRoute() !== 'state') {
            return $this->resultRedirectFactory->create()->setPath('checkout/index');
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set(__('Shipping destination'));
        return $page;
    }
}
