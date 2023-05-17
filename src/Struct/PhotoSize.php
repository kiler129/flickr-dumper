<?php
declare(strict_types=1);

namespace App\Struct;

/**
 * @see https://www.flickr.com/services/api/misc.urls.html
 * @deprecated This should be moved to App\Flickr\Enum namespace
 */
enum PhotoSize: string
{
    case SQUARE_75      = 'sq'; //this is largely undocummented
    case THUMB_75       = 's';
    case THUMB_100      = 't';
    case THUMB_150      = 'q';
    case SMALL_240      = 'm';
    case SMALL_320      = 'n';
    case SMALL_400      = 'w';
    case MEDIUM_500     = '';
    case MEDIUM_640     = 'z';
    case MEDIUM_800     = 'c';
    case LARGE_1024     = 'b';
    case LARGE_1600     = 'h';
    case LARGE_2048     = 'k'; //undocumented https://www.flickr.com/groups/51035612836@N01/discuss/72157636063789543/72157644356066041
    case XLARGE_3K      = '3k';
    case XLARGE_4K      = '4k';
    case XLARGE_4K_2to1 = 'f';
    case XLARGE_5K      = '5k';
    case XLARGE_6K      = '6k';
    case ORIGINAL       = 'o';

    /** @deprecated  */
    public const ALL = [
        self::SQUARE_75,
        self::THUMB_75,
        self::THUMB_100,
        self::THUMB_150,
        self::SMALL_240,
        self::SMALL_320,
        self::SMALL_400,
        self::MEDIUM_500,
        self::MEDIUM_640,
        self::MEDIUM_800,
        self::LARGE_1024,
        self::LARGE_1600,
        self::LARGE_2048,
        self::XLARGE_3K,
        self::XLARGE_4K,
        self::XLARGE_4K_2to1,
        self::XLARGE_5K,
        self::XLARGE_6K,
        self::ORIGINAL,
    ];

    /**
     * List of all sizes from largest to smallest
     */
    public const CASES_SIZE_DESCENDING = [
        self::ORIGINAL,
        self::XLARGE_6K,
        self::XLARGE_5K,
        self::XLARGE_4K_2to1,
        self::XLARGE_4K,
        self::XLARGE_3K,
        self::LARGE_2048,
        self::LARGE_1600,
        self::LARGE_1024,
        self::MEDIUM_800,
        self::MEDIUM_640,
        self::MEDIUM_500,
        self::SMALL_400,
        self::SMALL_320,
        self::SMALL_240,
        self::THUMB_150,
        self::THUMB_100,
        self::THUMB_75,
        self::SQUARE_75,
    ];

    public static function defaultSize(): self
    {
        return self::from('');
    }

    /**
     * Compares sizes and returns relationship between them
     *
     * Keep in mind that original size will always be considered as the largest as we're aiming to always compare
     * quality and not dimensions.
     *
     * @return int Standard spaceship-complain comparison
     *             (-1 if $this is smaller than $otherSize, 0 if equal, or +1 if $this is larger than $otherSize)
     */
    public function compareWith(PhotoSize $otherSize): int
    {
        return \array_search($this, self::CASES_SIZE_DESCENDING, true) <=>
               \array_search($otherSize, self::CASES_SIZE_DESCENDING, true);
    }

    /**
     * Various Flickr API methods allow requesting certain sizes of images. This returns the size in a correct format
     * for requests.
     *
     * @return string|null Some sizes cannot be requested
     */
    public function asApiField(): ?string
    {
        if ($this->value === '') {
            return null;
        }

        return 'url_' . $this->value;
    }

    /**
     * Gets all sizes as url request/response keys in size-descending order
     *
     * @return list<string>
     *
     * @deprecated It's likely not needed
     */
    public static function allAsUrlRequestsDescending(): array
    {
        static $allUrlRequests = null;
        if (isset($allUrlRequests)) {
            return $allUrlRequests;
        }

        $allUrlRequests = [];
        foreach (self::CASES_SIZE_DESCENDING as $size) {
            $rSize = $size->asApiField();
            if ($rSize !== null) {
                $allUrlRequests[] = $rSize;
            }
        }

        return $allUrlRequests;
    }
}
