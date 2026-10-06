<?php
// Render the real admin template against saved order snapshots, without Magento bootstrap.
namespace Magento\Framework {
    class DataObject {
        protected $data;
        public function __construct(array $data = []) { $this->data = $data; }
        public function getData($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
    }
    class Registry {
        public $data = [];
        public function registry($key) { return $this->data[$key] ?? null; }
    }
}
namespace Magento\Backend\Block\Template {
    class Context {}
}
namespace Magento\Backend\Block {
    class Template extends \Magento\Framework\DataObject {
        public $dateFormats = [];
        public function __construct($context, array $data = []) { parent::__construct($data); }
        public function escapeHtml($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
        public function escapeUrl($value) { return $this->escapeHtml($value); }
        public function formatDate($date, $format, $showTime, $timezone) {
            $this->dateFormats[] = [$date, $format, $showTime, $timezone];
            return (new \DateTimeImmutable($date, new \DateTimeZone($timezone)))->format('M j, Y');
        }
    }
}
namespace {
    function __($text) { return $text; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    require __DIR__ . '/../core/Model/OrderFflData.php';
    require __DIR__ . '/../core/Block/Adminhtml/Order/Ffl.php';

    function renderFfl($block) {
        ob_start();
        try {
            include __DIR__ . '/../core/view/adminhtml/templates/order/view/ffl.phtml';
            return ob_get_contents();
        } finally { ob_end_clean(); }
    }

    $registry = new \Magento\Framework\Registry();
    $reader = new \RefactoredGroup\AutoFflCore\Model\OrderFflData();
    $block = new \RefactoredGroup\AutoFflCore\Block\Adminhtml\Order\Ffl(
        new \Magento\Backend\Block\Template\Context(), $registry, $reader
    );
    check(trim(renderFfl($block)) === '', 'Missing orders must not render an FFL section.');
    $ordinary = new \Magento\Framework\DataObject(['increment_id' => 'ordinary-child']);
    $registry->data['current_order'] = $ordinary;
    check(trim(renderFfl($block)) === '', 'Ordinary multishipping child orders must not show dealer metadata.');

    $license = '5-75-121-07-6L-00199';
    $snapshot = ['license' => $license, 'id' => 921, 'expirationDate' => '2027-12-31',
        'uuid' => '11111111-2222-4333-8444-555555555555'];
    $shipping = new \Magento\Framework\DataObject(['firstname' => 'Jane', 'lastname' => 'Customer',
        'company' => 'Fixture Dealer', 'street' => ['123 Dealer Street']]);
    $order = new \Magento\Framework\DataObject(['ffl_license' => $license,
        'ffl_dealer_data' => json_encode($snapshot), 'shipping_address' => $shipping]);
    $beforeOrder = $order->getData();
    $beforeShipping = $shipping->getData();
    $registry->data['current_order'] = $order;
    $html = renderFfl($block);
    $document = new \DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new \DOMXPath($document);
    check($xpath->query('//section[contains(@class, "admin__page-section")]')->length === 1 &&
        $xpath->query('//table[@class="admin__table-secondary"]/tr')->length === 4,
        'FFL fields must appear in one native admin section with labeled table rows.');
    check(strpos($html, $license) !== false && strpos($html, 'Dec 31, 2027') !== false,
        'The panel must show the saved license and actual expiration date.');
    check(strpos($html, 'Automatic FFL Dealer ID') === false && strpos($html, '>921<') === false,
        'The internal dealer ID must not appear in the human-readable admin section.');
    check($block->dateFormats === [['2027-12-31', \IntlDateFormatter::MEDIUM, false, 'UTC']],
        'A date-only expiration must use the native admin date formatter without a timezone shift.');
    $links = $xpath->query('//a');
    check($links->length === 2 && $links[0]->textContent === 'Verify FFL' && $links[1]->textContent === 'View Certificate',
        'Certificate and eZ Check must be labeled clickable links instead of raw URLs.');
    check($links[0]->getAttribute('href') === $reader->fromOrder($order)['ezcheck_url'] &&
        $links[1]->getAttribute('href') === $reader->fromOrder($order)['certificate_url'] &&
        strpos($html, '&amp;licsDis=75&amp;licsSeq=00199') !== false,
        'The template must escape URLs without losing the license lookup or leading zeros.');
    foreach ($links as $link) {
        check($link->getAttribute('target') === '_blank' && $link->getAttribute('rel') === 'noopener noreferrer',
            'External FFL links must open safely in a separate tab.');
    }
    check($order->getData() === $beforeOrder && $shipping->getData() === $beforeShipping &&
        $xpath->query('//address')->length === 0 && strpos($html, 'Jane') === false,
        'Admin display must neither decorate shipping addresses nor modify order data.');

    $registry->data['current_order'] = new \Magento\Framework\DataObject([
        'ffl_license' => $license, 'ffl_dealer_data' => '{broken-json'
    ]);
    $legacyHtml = renderFfl($block);
    check(strpos($legacyHtml, $license) !== false && strpos($legacyHtml, 'Unavailable — verify with ATF eZ Check') !== false &&
        strpos($legacyHtml, 'View Certificate') === false && strpos($legacyHtml, 'Automatic FFL Dealer ID') === false,
        'Legacy orders must show available fields and explicitly label unknown expiration and certificate data.');
    $registry->data['current_order'] = new \Magento\Framework\DataObject(['ffl_dealer_data' => json_encode([
        'license' => '<script>alert(1)</script>', 'uuid' => 'javascript:alert(1)', 'expirationDate' => '<b>fake</b>'
    ])]);
    check(trim(renderFfl($block)) === '', 'Untrusted legacy metadata must not render injected HTML or links.');

    unset($registry->data['current_order']);
    $registry->data['order'] = $order;
    check($block->getFflData()['license'] === $license, 'The native order registry fallback must work.');
    $override = new \RefactoredGroup\AutoFflCore\Block\Adminhtml\Order\Ffl(
        new \Magento\Backend\Block\Template\Context(), $registry, $reader, ['order' => $ordinary]
    );
    check(trim(renderFfl($override)) === '', 'An explicitly supplied child order must take precedence over registry data.');

    $layout = simplexml_load_file(__DIR__ . '/../core/view/adminhtml/layout/sales_order_view.xml');
    $blocks = $layout->xpath('/page/body/referenceContainer[@name="order_additional_info"]/block');
    check(count($blocks) === 1 && (string) $blocks[0]['class'] === \RefactoredGroup\AutoFflCore\Block\Adminhtml\Order\Ffl::class &&
        (string) $blocks[0]['template'] === 'RefactoredGroup_AutoFflCore::order/view/ffl.phtml',
        'The FFL section must use the native additional order information container.');
    echo "Readable FFL admin section, date formatting, legacy fallback, order isolation, and escaping checks passed.\n";
}
