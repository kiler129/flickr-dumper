<?php
declare(strict_types=1);

namespace App\Flickr\UseCase;

use App\Exception\DomainException;
use App\Flickr\Struct\PhotoVariant;
use App\Flickr\Url\UrlParser;
use App\Struct\PhotoSize;

class ResolvePhotoVariants
{
    public function __construct(private UrlParser $urlParser)
    {
    }

    /**
     * Modified existing/default API extras to include all photo variants to be resolved later from response
     *
     * You can also add PhotoSize->asUrlRequest or PhotoExtraFields::URL_* constants (should this be unified? @todo)
     */
    public function addVariantsToRequest(array $extras): array
    {
        return \array_merge($extras, PhotoSize::allAsUrlRequestsDescending());
    }

    /**
     * Finds the largest variant from the API response
     *
     * Technically an alternative method could be implemented that sorts sizes, but it would be expensive to convert all
     *
     * @return PhotoVariant
     */
    public function findLargestVariant(array $apiResponseKV): PhotoVariant
    {
        static $casesDescending = null;
        if ($casesDescending === null) {
            $casesDescending = PhotoSize::allAsUrlRequestsDescending();
        }

        foreach ($casesDescending as $case) {
            if (!isset($apiResponseKV[$case])) {
                continue;
            }

            return PhotoVariant::createFromFilename(
                $this->urlParser->getStaticFilename($apiResponseKV[$case])
            );
        }

        throw new DomainException(
            "Unable to find largest variant. Passed K-V response contains keys: " .
            \implode('", "', \array_keys($apiResponseKV))
        );
    }
}
