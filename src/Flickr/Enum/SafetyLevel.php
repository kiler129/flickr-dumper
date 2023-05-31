<?php
declare(strict_types=1);

namespace App\Flickr\Enum;

/**
 * Sets how safe a picture/video is
 * In general see: https://www.flickrhelp.com/hc/en-us/articles/4404057968788-Account-has-been-reviewed-as-moderate-or-restricted
 *
 * There are two forms of safety level on Flickr. Both are the same but shifted by one (sic!).
 *
 * When reading and interpreting values returned by the API (e.g. "flickr.photos.getInfo") the "safety_level" will be
 * 0-indexed, i.e.:
 *   0 => Safe
 *   1 => Moderate
 *   2 => Restricted
 *
 * This is NOT documented anywhere but you can e.g. do "flickr.photos.getInfo" call for:
 *  - 52723297367 (Safe, returns "0")
 *  - 3983309743 (Moderate, returns "1")
 *  - 29859929628 (Restricted, returns "2")
 * (yes, these are returned as strings on top of everything)
 *
 *
 * If you're SETTING see "safety_level" on https://www.flickr.com/services/api/flickr.photos.setSafetyLevel.html and
 * "toSettableValue()", which will return:
 *   1 => Safe
 *   2 => Moderate
 *   3 => Restricted
 *
 * Go figure :D
 */
enum SafetyLevel: int
{
    case SAFE = 0;
    case MODERATE = 1;
    case RESTRICTED = 2;

    private const LABELS = [
        self::SAFE->value => 'Safe',
        self::MODERATE->value => 'Moderate',
        self::RESTRICTED->value => 'Restricted',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    public function isSafe(): bool
    {
        return $this->value === self::SAFE->value;
    }

    public function toApiSettableValue(): int
    {
        return $this->value + 1;
    }
}
