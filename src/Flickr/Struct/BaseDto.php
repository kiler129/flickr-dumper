<?php
declare(strict_types=1);

namespace App\Flickr\Struct;

use App\Exception\DomainException;
use App\Exception\InvalidArgumentException;

abstract class BaseDto
{
    protected const SIMPLE_TYPECAST_MAP = [];
    protected const KNOWN_TO_API = [];

    protected array $dataTransformed = [];

    protected function __construct(public readonly array $apiData)
    {
    }

    final public function __set(string $name, mixed $value): void
    {
        throw new InvalidArgumentException(\sprintf("%s is immutable", static::class));
    }

    final public function __get(string $name): mixed
    {
        if (isset(static::KNOWN_TO_API[$name])) {
            $name = static::KNOWN_TO_API[$name];
        }

        if (isset($this->dataTransformed[$name])) {
            return $this->dataTransformed[$name];
        }

        if (!\array_key_exists($name, $this->apiData)) {
            throw new DomainException('Property ' . $name . ' is not present in the dataset');
        }

        return $this->dataTransformed[$name] = $this->transformValue($name, $this->apiData[$name]);
    }

    final public function __isset(string $name): bool
    {
        return \array_key_exists(static::KNOWN_TO_API[$name] ?? $name, $this->apiData);
    }

    abstract protected function transformValue(string $apiName, mixed $value): mixed;

    final protected function transformAutocast(string $apiName, mixed $value): mixed
    {
        if (isset(static::SIMPLE_TYPECAST_MAP[$apiName])) {
            \settype($value, static::SIMPLE_TYPECAST_MAP[$apiName]);
        }

        return $value;
    }

    final protected function castDateTime(int|string $value): \DateTimeInterface
    {
        if ((string)(int)$value === (string)$value) {
            $dti = new \DateTimeImmutable();
            return $dti->setTimestamp((int)$value);
        }

        return new \DateTimeImmutable($value);
    }

    static public function fromGenericApiResponse(array $fields): static
    {
        return new static($fields);
    }
}
