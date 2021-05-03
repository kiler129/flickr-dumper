<?php
declare(strict_types=1);

namespace App\Exception\Api;

class UnexpectedResponseException extends ApiCallException
{
    public static function create(string $detailedMessage, array $apiResponse): static
    {
        return self::createFromCode(self::UNEXPECTED_RESPONSE, $detailedMessage, null, \json_encode($apiResponse));
    }
}
