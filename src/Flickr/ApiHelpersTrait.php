<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\Api\BadApiMethodCallException;

trait ApiHelpersTrait
{
    private function validatePaginationValues(int $page, int $perPage): void
    {
        if ($page < 1) {
            throw new BadApiMethodCallException(\sprintf('Page # must be a positive integer or null (got %d)', $page));
        }

        if ($perPage < 1 || $perPage > static::MAX_PER_PAGE) {
            throw new BadApiMethodCallException(\sprintf('Per page must be between 1 and %d (got %d)', self::MAX_PER_PAGE, $perPage));
        }
    }
}
