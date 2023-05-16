<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\LogicException;
use App\Exception\RuntimeException;

/** @deprecated Use ApiClientConfigFactory */
class ApiKeyProvider
{
    private array $keys;

    public function __construct(?array $flickrApiKeys)
    {
        $this->keys = \array_values($flickrApiKeys ?? []);
    }

    public function getFirst(): string
    {
        if (!isset($this->keys[0])) {
            throw new RuntimeException('Cannot retrieve a Flickr API key - no keys configured');
        }

        return $this->keys[0];
    }

    public function getRandom(): string
    {
        $count = \count($this->keys);
        if ($count < 2) {
            throw new LogicException(
                \sprintf(
                    'Cannot retrieve a random Flick API key - at least 2 keys are required (configured %d)',
                    $count
                )
            );
        }

        return $this->keys[\random_int(0, $count-1)];
    }

    /**
     * @return list<string>
     */
    public function getAll(): array
    {
        return $this->keys;
    }
}
