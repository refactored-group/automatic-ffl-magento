<?php
namespace RefactoredGroup\AutoFflCore\Controller\Routing;

use Magento\Checkout\Model\Session;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\CartRepositoryInterface;

class State extends Action implements HttpPostActionInterface
{
    private $session;
    private $regions;
    private $validator;
    private $quotes;
    private $jsonFactory;

    public function __construct(
        Context $context,
        Session $session,
        CollectionFactory $regions,
        Validator $validator,
        CartRepositoryInterface $quotes,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->session = $session;
        $this->regions = $regions;
        $this->validator = $validator;
        $this->quotes = $quotes;
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        if (!$this->validator->validate($this->getRequest())) {
            return $result->setHttpResponseCode(403)->setData(['error' => 'Invalid form key']);
        }
        $state = strtoupper(trim((string) $this->getRequest()->getParam('state')));
        if (!preg_match('/^[A-Z]{2}$/', $state)) {
            return $result->setHttpResponseCode(422)->setData(['error' => 'Invalid state']);
        }
        $region = $this->regions->create()
            ->addFieldToFilter('country_id', ['eq' => 'US'])
            ->addFieldToFilter('code', ['eq' => $state])
            ->getFirstItem();
        if (!$region->getId()) {
            return $result->setHttpResponseCode(422)->setData(['error' => 'Invalid state']);
        }
        $quote = $this->session->getQuote();
        $quote->setFflRoutingState($state);
        $quote->setFflLicense(null);
        $quote->setFflDealerData(null);
        $this->quotes->save($quote);
        return $result->setData(['state' => $state]);
    }
}
