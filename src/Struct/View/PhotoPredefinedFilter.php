<?php
declare(strict_types=1);

namespace App\Struct\View;

readonly final class PhotoPredefinedFilter
{
    private const FILTERS = [
        [
            'name' => 'No votes',
            'filter' => [
                'localStats.upVotes' => 0,
                'localStats.downVotes' => 0,
            ],
        ],
        [
            'name' => 'With upvotes',
            'filter' => [
                'localStats.upVotes' => '!0',
            ],
        ],
        [
            'name' => 'With downvotes',
            'filter' => [
                'localStats.downVotes' => '!0',
            ],
        ],
    ];


    static public function getAll(): array
    {
        return self::FILTERS;
    }
}
