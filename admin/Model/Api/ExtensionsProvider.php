<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Model\Api;

use RefactoredGroup\AutoFflAdmin\Model\Api\Response\ApiResponseInterface;
use RefactoredGroup\AutoFflAdmin\Model\Api\Response\ApiResponseInterfaceFactory;
use Laminas\Http\Request;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;

class ExtensionsProvider
{
    public const USER_AGENT = 'automatic-ffl-magento';
    public const ACCEPT_TYPE = 'application/vnd.github+json';
    public const X_API_VERSION = '2022-11-28';
    public const GITHUB_API_URN = 'api.github.com/repos/refactored-group/automatic-ffl-magento/releases/latest';

    /**
     * @var CurlFactory
     */
    private $curlFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ApiResponseInterfaceFactory
     */
    private $apiResponseFactory;

    public function __construct(
        CurlFactory $curlFactory,
        SerializerInterface $serializer,
        ApiResponseInterfaceFactory $apiResponseFactory
    ) {
        $this->curlFactory = $curlFactory;
        $this->serializer = $serializer;
        $this->apiResponseFactory = $apiResponseFactory;
    }

    /**
     * @param string $url
     * 
     * @return ApiResponseInterface
     */
    public function getApiResponse(string $url): ApiResponseInterface
    {
        $curlObject = $this->curlFactory->create();
        $headers = $this->getHeaders();
        $curlObject->write(Request::METHOD_GET, $url, '1.1', $headers);
        $result = $curlObject->read();

        $apiResponse = $this->apiResponseFactory->create();
        if ($result === false || $result === '') {
            return $apiResponse;
        }
        $result = preg_split('/^\r?$/m', $result, 2);

        $result = trim($result[1]);
        $apiResponse->setContent($result);
        $curlObject->close();

        return $apiResponse;
    }

    /**
     * @return array
     */
    public function getApiModuleData(): array
    {
        $apiResponse = $this->getApiResponse(
            $this->formatUrl(self::GITHUB_API_URN)
        );
        $moduleData = [];
        if ($apiResponse) {
            $moduleData = $this->serializer->unserialize($apiResponse->getContent());
        }

        return $moduleData;
    }

    private function formatUrl(string $urn): string
    {
        return 'https://' . $urn;
    }

    private function getHeaders(): array
    {
        $headers = [
            'User-Agent: ' . self::USER_AGENT,
            'Accept: ' . self::ACCEPT_TYPE,
            'X-GitHub-Api-Version: ' . self::X_API_VERSION,
        ];

        return $headers;
    }
}
