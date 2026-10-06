<?php
// Restriction lookups may retry transient failures but must remain fail-closed.
namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface {
        public function getValue($path, $scope = null, $scopeCode = null);
    }
}
namespace Magento\Framework\Exception {
    class LocalizedException extends \Exception {}
}
namespace Magento\Framework\HTTP\Client {
    class CurlFactory {
        public $responses;
        public $created = 0;
        public function __construct(array $responses) { $this->responses = $responses; }
        public function create() {
            $response = $this->responses[$this->created] ?? end($this->responses);
            $this->created++;
            return new ScriptedCurl($response);
        }
    }
    class ScriptedCurl {
        private $response;
        public function __construct(array $response) { $this->response = $response; }
        public function setTimeout($seconds) {}
        public function setOption($option, $value) {}
        public function addHeader($name, $value) {}
        public function get($url) { $this->perform(); }
        public function post($url, $body) { $this->perform(); }
        public function getStatus() { return $this->response['status']; }
        public function getBody() { return $this->response['body']; }
        private function perform() {
            if (!empty($this->response['throw'])) {
                throw new \RuntimeException('fixture transport failure');
            }
        }
    }
}
namespace Magento\Store\Model {
    class ScopeInterface { const SCOPE_STORE = 'stores'; }
}
namespace Psr\Log {
    interface LoggerInterface {}
}
namespace RefactoredGroup\AutoFflCore\Helper {
    class Data {
        const XML_PATH_STORE_HASH = 'hash';
        const XML_PATH_SANDBOX_MODE = 'sandbox';
        const API_SANDBOX_URL = 'https://sandbox.invalid';
        const API_PRODUCTION_URL = 'https://production.invalid';
    }
}
namespace {
    if (!defined('CURLOPT_CONNECTTIMEOUT')) define('CURLOPT_CONNECTTIMEOUT', 78);
    if (!defined('CURLOPT_FOLLOWLOCATION')) define('CURLOPT_FOLLOWLOCATION', 52);
    function __($text) { return $text; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }

    require __DIR__ . '/../core/Model/RestrictionsClient.php';

    class ScopeConfig implements \Magento\Framework\App\Config\ScopeConfigInterface {
        public function getValue($path, $scope = null, $scopeCode = null) {
            return $path === 'hash' ? 'fixture-store' : true;
        }
    }
    class Logger implements \Psr\Log\LoggerInterface {
        public $warnings = [];
        public function warning($message, array $context = []) { $this->warnings[] = $context; }
    }

    $validPolicy = json_encode(['magento_policy_supported' => true, 'ammo_states' => []]);
    $logger = new Logger();
    $factory = new \Magento\Framework\HTTP\Client\CurlFactory([
        ['status' => 503, 'body' => 'temporarily unavailable'],
        ['status' => 200, 'body' => $validPolicy]
    ]);
    $client = new \RefactoredGroup\AutoFflCore\Model\RestrictionsClient(
        new ScopeConfig(), $factory, $logger
    );
    check($client->getPolicy(1)['magento_policy_supported'] === true,
        'A transient 5xx response must retry once and return the verified policy.');
    check($factory->created === 2 && count($logger->warnings) === 1,
        'The retry must be bounded and the initial failure must be logged.');
    check($logger->warnings[0]['path'] === 'restrictions' && $logger->warnings[0]['status'] === 503,
        'Diagnostics must identify the safe endpoint path and HTTP status.');

    $logger = new Logger();
    $factory = new \Magento\Framework\HTTP\Client\CurlFactory([
        ['status' => 400, 'body' => 'bad request'],
        ['status' => 200, 'body' => $validPolicy]
    ]);
    $client = new \RefactoredGroup\AutoFflCore\Model\RestrictionsClient(
        new ScopeConfig(), $factory, $logger
    );
    try {
        $client->getPolicy(1);
        throw new \RuntimeException('A non-retryable response must fail closed.');
    } catch (\Magento\Framework\Exception\LocalizedException $e) {
        check($factory->created === 1, 'A non-retryable 4xx response must not be repeated.');
    }

    $logger = new Logger();
    $factory = new \Magento\Framework\HTTP\Client\CurlFactory([
        ['status' => 0, 'body' => '', 'throw' => true],
        ['status' => 0, 'body' => '', 'throw' => true]
    ]);
    $client = new \RefactoredGroup\AutoFflCore\Model\RestrictionsClient(
        new ScopeConfig(), $factory, $logger
    );
    try {
        $client->getPolicy(1);
        throw new \RuntimeException('Repeated transport failures must fail closed.');
    } catch (\Magento\Framework\Exception\LocalizedException $e) {
        check($factory->created === 2 && count($logger->warnings) === 2,
            'A transport failure must make exactly two attempts and log each one.');
        check($logger->warnings[0]['exception'] === 'RuntimeException',
            'Transport diagnostics must record the exception type without its sensitive message.');
    }

    echo "Restrictions client retry and fail-closed smoke checks passed.\n";
}
