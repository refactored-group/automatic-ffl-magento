<?php
/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
namespace RefactoredGroup\AutoFflAdmin\Model\Api\Response;

interface ApiResponseInterface
{
    public function getContent(): ?string;

    public function setContent(?string $content): ApiResponseInterface;
}
