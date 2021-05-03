<?php
declare(strict_types=1);

namespace App\Exception\Api;

class DecodeException extends ApiCallException
{
    public static function create(string $detailedMessage, \Throwable $previous, string $data): static
    {
        return self::createFromCode(self::LOCAL_ERROR, $detailedMessage, $previous, $data);
    }
}
