<?php
declare(strict_types=1);

namespace App\Struct;

/** @deprecated This should be an enum used by the API as well */
final class PrivacyFilter
{
    public const PUBLIC_ENTITY          = 1;
    public const PRIVATE_FRIENDS        = 2;
    public const PRIVATE_FAMILY         = 3;
    public const PRIVATE_FRIENDS_FAMILY = 4;
    public const PRIVATE_ENTITY         = 4;

    public const ALL = [
        self::PUBLIC_ENTITY,
        self::PRIVATE_FRIENDS,
        self::PRIVATE_FAMILY,
        self::PRIVATE_FRIENDS_FAMILY,
        self::PRIVATE_ENTITY,
    ];
}
