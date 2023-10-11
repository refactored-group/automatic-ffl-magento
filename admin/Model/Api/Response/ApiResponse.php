<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Model\Api\Response;

use Magento\Framework\DataObject;

class ApiResponse extends DataObject implements ApiResponseInterface
{
    public const CONTENT = 'content';

    public function getContent(): ?string
    {
        return $this->getData(self::CONTENT);
    }

    public function setContent(?string $content): ApiResponseInterface
    {
        $this->setData(self::CONTENT, $content);

        return $this;
    }
}
