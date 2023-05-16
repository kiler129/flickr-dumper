<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\UnexpectedResponseException;
use App\Flickr\Struct\ApiResponse;
use App\Struct\PhotoExtraFields;

trait ApiEndpointHelper
{
    private function validatePaginationValues(int $page, int $perPage): void
    {
        if ($page < 1) {
            throw new BadApiMethodCallException(\sprintf('Page # must be a positive integer or null (got %d)', $page));
        }

        if ($perPage < 1 || $perPage > static::MAX_PER_PAGE) {
            throw new BadApiMethodCallException(
                \sprintf('Per page must be between 1 and %d (got %d)', self::MAX_PER_PAGE, $perPage)
            );
        }
    }

    /** @deprecated use serializeExtras */
    private function normalizeExtrasToParams(array &$params, array $extras): void
    {
        if (\count($extras) === 0) {
            return;

        }
        $params['extras'] = $this->serializeExtras($extras);
    }

    private function serializeExtras(array $extras): string
    {
        $normalizedExtras = [];
        $invalidAttrs = [];

        foreach ($extras as $attr) {
            //@deprecated TODO don't allow for strings ffs... so we don't have to do dumb checks

            if ($attr instanceof PhotoExtraFields) {
                $normalizedExtras[] = $attr->value;
                continue;
            }

            if (\is_string($attr) && PhotoExtraFields::tryFrom($attr) !== null) {
                $normalizedExtras[] = $attr;
                continue;
            }

            $invalidAttrs[] = $attr;
        }

        if (\count($invalidAttrs) !== 0) {
            throw new BadApiMethodCallException(
                \sprintf('Invalid extras value(s) specified: %s', implode('", "', $invalidAttrs))
            );
        }

        return \implode(',', $normalizedExtras);
    }

    /**
     * @param callable(int $page): ApiResponse $getPaged
     *
     * @return iterable<array<mixed>>
     */
    private function flattenPages(callable $getPaged, string $container): iterable
    {
        $page = 1;
        $totalPages = null;

        do {
            $rsp = $getPaged($page)->getContent();
            if ($totalPages === null) { //first iteration
                if (!isset($rsp['pages'])) {
                    throw UnexpectedResponseException::create('API did not return number of pages', $rsp);
                }

                $totalPages = (int)$rsp['pages'];
            }

            if (!isset($rsp[$container])) {
                throw UnexpectedResponseException::create(
                    \sprintf('API did not return container "%s" for %d page', $container, $page),
                    $rsp
                );
            }

            yield from $rsp[$container];
        } while (++$page <= $totalPages);
    }
}
