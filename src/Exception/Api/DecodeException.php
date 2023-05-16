<?php
declare(strict_types=1);

namespace App\Exception\Api;

use App\Struct\ApiError;

/** @deprecated  */
class DecodeException extends ApiCallException
{
    public static function create(string $detailedMessage, \Throwable $previous, string $data): static
    {
        return self::createFromError(ApiError::LOCAL_ERROR, $detailedMessage, $previous, $data);
    }
}
