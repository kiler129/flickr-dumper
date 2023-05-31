<?php
declare(strict_types=1);

namespace App\Struct\View;

readonly final class PhotoSuggestedSort
{
    private const FIELDS = [
        'remoteStats.views' => '👀 Remote views',
        'remoteStats.favorites' => '⭐ Remote favorites',
        'remoteStats.comments' => '💬 Remote comments',
        'localStats.views' => '👀 Local views',
        'localStats.upVotes' => '👍 Local up votes',
        'localStats.downVotes' => '👎 Local down votes',
        'dateTaken' => '📷 Date taken',
        'dateUploaded' => '☁️ Date uploaded',
        'dateLastRetrieved' => '💾 Date downloaded',
    ];

    private const DIRECTION_ASC = [
        'remoteStats.views' => '↓',
        'remoteStats.favorites' => '↓',
        'remoteStats.comments' => '↓',
        'localStats.views' => '↓',
        'localStats.upVotes' => '↓',
        'localStats.downVotes' => '↓',
        'dateTaken' => 'old to new',
        'dateUploaded' => 'old to new',
        'dateLastRetrieved' => 'old to new',
    ];

    private const DIRECTION_DESC = [
        'remoteStats.views' => '↑',
        'remoteStats.favorites' => '↑',
        'remoteStats.comments' => '↑',
        'localStats.views' => '↑',
        'localStats.upVotes' => '↑',
        'localStats.downVotes' => '↑',
        'dateTaken' => 'new to old',
        'dateUploaded' => 'new to old',
        'dateLastRetrieved' => 'new to old',
    ];

    static public function getAll(): array
    {
        $out = [];
        foreach (self::FIELDS as $field => $name) {
            $out[$field] = [
                'name' => $name,
                'dir' => [
                    'DESC' => self::DIRECTION_DESC[$field],
                    'ASC' => self::DIRECTION_ASC[$field],
                ]
            ];
        }

        return $out;
    }
}
