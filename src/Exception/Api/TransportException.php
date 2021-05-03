<?php
declare(strict_types=1);

namespace App\Exception\Api;

class TransportException extends ApiCallException
{
    public static function create(string $detailedMessage, \Throwable $previous = null): static
    {
        return self::createFromCode(self::LOCAL_ERROR, $detailedMessage, $previous);
    }
}
