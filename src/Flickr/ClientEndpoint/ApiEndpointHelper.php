<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\UnexpectedResponseException;
use App\Flickr\Enum\PhotoExtraFields;
use App\Flickr\Struct\ApiResponse;

/**
 * @internal
 */
trait ApiEndpointHelper
{
    //Well-known constants for token-based pagination. See e.g. https://www.flickr.com/services/api/flickr.galleries.getList.html
    public const CONTINUATION_START_TOKEN = '0';
    public const CONTINUATION_LAST_TOKEN =  '-1';

    private function validateRegularPaginationValues(int $page, int $perPage): void
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

    private function validateTokenPaginationValues(?int $page, int $perPage, ?string $continuationToken): void
    {
        if ($page === null && $continuationToken === null) {
            //In practice, usually it causes either API error OR returns all results without pagination OR timeout ;)
            throw new BadApiMethodCallException(
                'Either page # must be set OR continuation token must be non-empty. ' .
                'Passing both null-values to both is undefined.'
            );
        }

        if ($continuationToken === null) {
            if ($page === null) {
                throw new BadApiMethodCallException(
                    'When using regular (non token-based) pagination the page cannot be null'
                );
            }

            $this->validateRegularPaginationValues($page, $perPage);
        }


        if ($continuationToken === '') {
            throw new BadApiMethodCallException(
                'When using token-based pagination the token cannot be an empty string'
            );
        }

        if ($page !== null) {
            throw new BadApiMethodCallException(
                \sprintf('When using token-based pagination the page must be null (got %d)', $page)
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
                \trigger_error('Passing strings to serializeExtras is deprecated', \E_USER_DEPRECATED);
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
     * Flattens a list of API-returned elements for regular pagination (page-based)
     *
     * @param callable(int $page): ApiResponse $getPaged
     * @param callable(int $page): void $pageFinishCb
     *
     * @return iterable<array<mixed>>
     */
    private function flattenRegularPages(callable $getPaged, ?callable $pageFinishCb, string $container): iterable
    {
        $page = 1;
        $totalPages = null;
        if ($pageFinishCb === null) {
            $pageFinishCb = function (): void {};
        }

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
            $pageFinishCb($page);
        } while (++$page <= $totalPages);
    }

    /**
     * Flattens a list of API-returned elements for token-based pagination ("continuation"-based)
     *
     * For token-based pagination, as far as $pageFinishCb is concerned the page numbers are emulated for consistency.
     *
     * @param callable(string $continuationToken): ApiResponse $getTokenized
     * @param callable(int $page): void $pageFinishCb
     *
     * @return iterable<array<mixed>>
     */
    private function flattenPagesTokenized(callable $getTokenized, ?callable $pageFinishCb, string $container): iterable
    {
        $virtualPage = 1;
        $contToken = self::CONTINUATION_START_TOKEN;
        if ($pageFinishCb === null) {
            $pageFinishCb = function (): void {};
        }

        do {
            $rsp = $getTokenized($contToken)->getContent();
            if (!isset($rsp['continuation'])) {
                throw UnexpectedResponseException::create('API did not return the continuation token', $rsp);
            }

            $contToken = (string)$rsp['continuation']; //sometimes they return int for the last one so we must cast!
            if (!isset($rsp[$container])) {
                throw UnexpectedResponseException::create(
                    \sprintf('API did not return container "%s" for %d page', $container, $virtualPage),
                    $rsp
                );
            }

            yield from $rsp[$container];
            $pageFinishCb($virtualPage);
            ++$virtualPage;
        } while ($contToken !== self::CONTINUATION_LAST_TOKEN);
    }
}
