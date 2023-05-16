<?php
declare(strict_types=1);

namespace App\Exception\Api;

use App\Struct\ApiError;

class UnexpectedResponseException extends ApiCallException
{
    public static function create(string $detailedMessage, array $apiResponse): static
    {
        return self::createFromError(ApiError::UNEXPECTED_RESPONSE, $detailedMessage, null, \json_encode($apiResponse));
    }
}
