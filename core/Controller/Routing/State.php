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
use Magento\Customer\Model\Session as CustomerSession;
use RefactoredGroup\AutoFflCore\Helper\Data;
use RefactoredGroup\AutoFflCore\Model\AddressHandoff;

class State extends Action implements HttpPostActionInterface
{
    private $session;
    private $regions;
    private $validator;
    private $quotes;
    private $jsonFactory;
    private $helper;
    private $customers;
    private $handoff;

    public function __construct(
        Context $context,
        Session $session,
        CollectionFactory $regions,
        Validator $validator,
        CartRepositoryInterface $quotes,
        JsonFactory $jsonFactory,
        Data $helper,
        CustomerSession $customers,
        AddressHandoff $handoff
    ) {
        parent::__construct($context);
        $this->session = $session;
        $this->regions = $regions;
        $this->validator = $validator;
        $this->quotes = $quotes;
        $this->jsonFactory = $jsonFactory;
        $this->helper = $helper;
        $this->customers = $customers;
        $this->handoff = $handoff;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        if (!$this->validator->validate($this->getRequest())) {
            return $result->setHttpResponseCode(403)->setData(['error' => 'Invalid form key']);
        }
        $quote = $this->session->getQuote();
        if (!$this->helper->isEnabled() || !$quote->getId() || !$quote->hasItems()) {
            return $result->setHttpResponseCode(409)->setData(['error' => 'The cart is unavailable.']);
        }
        $address = json_decode((string) $this->getRequest()->getParam('address', '{}'), true);
        $address = is_array($address) ? $address : [];
        if ($address && ($address['countryId'] ?? '') !== 'US') {
            return $result->setHttpResponseCode(422)->setData(['error' => 'Select a US delivery state to check ammunition shipping.']);
        }
        $state = strtoupper(trim((string) $this->getRequest()->getParam('state', $address['regionCode'] ?? '')));
        if ($state === '') {
            $state = strtoupper(trim((string) ($address['regionCode'] ?? '')));
        }
        if ($state === '' && !empty($address['regionId'])) {
            $region = $this->regions->create()
                ->addFieldToFilter('country_id', ['eq' => 'US'])
                ->addFieldToFilter('region_id', ['eq' => (int) $address['regionId']])
                ->getFirstItem();
            $state = (string) $region->getCode();
        }
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
        if ($quote->getFflRoutingState() !== $state) {
            if ($quote->getFflLicense() || $quote->getFflDealerData()) {
                $shipping = $quote->getShippingAddress();
                foreach (['firstname', 'lastname', 'company', 'street', 'city', 'country_id', 'region',
                    'region_id', 'postcode', 'telephone', 'shipping_method', 'ffl_license'] as $field) {
                    $shipping->unsetData($field);
                }
                $shipping->setCollectShippingRates(true);
                $quote->setTotalsCollectedFlag(false);
            }
            $quote->setFflRoutingState($state);
            $quote->setFflLicense(null);
            $quote->setFflDealerData(null);
        }
        $this->quotes->save($quote);
        if ($address) {
            $this->handoff->capture($quote, $address, $region->getId());
        }
        $route = $this->helper->getCheckoutRoute($quote, $state);
        if ($route === 'multishipping' && !$this->helper->isMultishippingCheckoutAvailable()) {
            $route = 'unavailable';
        }
        return $result->setData([
            'state' => $state,
            'route' => $route,
            'requiresDealer' => $this->helper->isFfl(),
            'requiresLogin' => !$this->customers->isLoggedIn(),
            'url' => $this->_url->getUrl($route === 'multishipping' ? 'multishipping/checkout'
                : ($route === 'unavailable' ? 'checkout/cart' : 'checkout/index'))
        ]);
    }
}
