<?php
namespace RefactoredGroup\AutoFflCore\Plugin;

use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use RefactoredGroup\AutoFflCore\Model\OrderFflData;

class OrderRepositoryPlugin
{
    private $extensionFactory;
    private $fflData;

    public function __construct(OrderExtensionFactory $extensionFactory, OrderFflData $fflData)
    {
        $this->extensionFactory = $extensionFactory;
        $this->fflData = $fflData;
    }

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order)
    {
        return $this->populate($order);
    }

    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $result)
    {
        foreach ($result->getItems() as $order) {
            $this->populate($order);
        }
        return $result;
    }

    public function afterSave(OrderRepositoryInterface $subject, OrderInterface $order)
    {
        // These fields are projections, not a way to overwrite the selected dealer.
        return $this->populate($order);
    }

    private function populate(OrderInterface $order)
    {
        if (!$order instanceof DataObject) {
            return $order;
        }
        $data = $this->fflData->fromOrder($order);
        $extension = $order->getExtensionAttributes();
        if ($extension === null && $data['license'] === null) {
            return $order;
        }
        $extension = $extension ?: $this->extensionFactory->create();
        $extension->setAutofflLicense($data['license']);
        $extension->setAutofflDealerId($data['dealer_id']);
        $extension->setAutofflExpirationDate($data['expiration_date']);
        $extension->setAutofflCertificateUrl($data['certificate_url']);
        $extension->setAutofflEzcheckUrl($data['ezcheck_url']);
        $order->setExtensionAttributes($extension);
        return $order;
    }
}
