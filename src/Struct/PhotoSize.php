<?php
declare(strict_types=1);

namespace App\Struct;

final class PhotoSize
{
    public const THUMB_75       = 's';
    public const THUMB_150      = 'q';
    public const THUMB_100      = 't';
    public const SMALL_240      = 'm';
    public const SMALL_320      = 'n';
    public const SMALL_400      = 'w';
    public const MEDIUM_640     = 'z';
    public const MEDIUM_800     = 'c';
    public const LARGE_1024     = 'b';
    public const LARGE_1600     = 'h';
    public const LARGE_2048     = 'k';
    public const XLARGE_3K      = '3k';
    public const XLARGE_4K      = '4k';
    public const XLARGE_4K_2to1 = 'f';
    public const XLARGE_5K      = '5k';
    public const XLARGE_6K      = '6k';
    public const ORIGINAL       = 'o';

    public const ALL = [
        self::THUMB_75,
        self::THUMB_150,
        self::THUMB_100,
        self::SMALL_240,
        self::SMALL_320,
        self::SMALL_400,
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
}
