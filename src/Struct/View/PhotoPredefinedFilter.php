<?php
declare(strict_types=1);

namespace App\Struct\View;

use App\Flickr\Enum\SafetyLevel;

readonly final class PhotoPredefinedFilter
{
    private const FILTERS = [
        [
            'name' => '🗳️ No votes',
            'filter' => [
                'localStats.upVotes' => 0,
                'localStats.downVotes' => 0,
            ],
        ],
        [
            'name' => '👍 With upvotes',
            'filter' => [
                'localStats.upVotes' => '!0',
            ],
        ],
        [
            'name' => '👀 No local views',
            'filter' => [
                'localStats.views' => 0,
            ],
        ],
        [
            'name' => '👀 With local views',
            'filter' => [
                'localStats.views' => '!0',
            ],
        ],
        [
            'name' => '👎 With downvotes',
            'filter' => [
                'localStats.downVotes' => '!0',
            ],
        ],
        [
            'name' => '🗳️👀 No votes w/local views',
            'filter' => [
                'localStats.upVotes' => 0,
                'localStats.downVotes' => 0,
                'localStats.views' => '!0',
            ],
        ],
        [
            'name' => '🏡 Only safe',
            'filter' => [
                'safetyLevel' => SafetyLevel::SAFE->value
            ],
        ],
        [
            'name' => '🔞 Only moderate & restricted',
            'filter' => [
                'safetyLevel' => '!' . SafetyLevel::SAFE->value
            ],
        ],
    ];


    static public function getAll(): array
    {
        return self::FILTERS;
    }
}
