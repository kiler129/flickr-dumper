<?php
declare(strict_types=1);

namespace App\Flickr\Url;

use App\Exception\RuntimeException;

final class UrlToolbox
{
    public function __construct(
        public readonly UrlParser $parse,
        public readonly UrlGenerator $generate,
    ) {
    }

    public function convertItemViewToCollectionView(): string
    {
        //essentially convert between plural and singular forms of collections (see regexes in UrlParser)
        throw new RuntimeException('Not implemented');
    }
}
