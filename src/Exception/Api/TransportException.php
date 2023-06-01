<?php
declare(strict_types=1);

namespace App\Exception\Api;

use App\Flickr\Enum\ApiError;

class TransportException extends ApiCallException
{
    public static function create(string $detailedMessage, \Throwable $previous = null): static
    {
        return self::createFromError(ApiError::LOCAL_ERROR, $detailedMessage, $previous);
    }
}
