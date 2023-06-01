<?php
declare(strict_types=1);

namespace App\Exception\Api;

use App\Flickr\Enum\ApiError;

class ApiCallException extends \DomainException
{
    private ?string $data;

    private function __construct(
        ?string $message = null,
        int $code = ApiError::UNKNOWN_ERROR->value,
        \Throwable $previous = null,
        ?string $data = null
    ) {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    static public function createFromError(
        int|ApiError $error,
        ?string $detailedMessage = null,
        \Throwable $previous = null,
        ?string $data = null
    ): static {
        if (\is_int($error)) {
            $error = ApiError::tryFrom($error) ?? ApiError::UNKNOWN_ERROR;
        }

        $message = $detailedMessage === null
            ? $error->description()
            : sprintf('%s (#%d: %s)', $detailedMessage, $error->value, $error->description());

        return new static($message, $error->value, $previous, $data);
    }

    public function getData(): ?string
    {
        return $this->data;
    }
}
