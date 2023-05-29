<?php
declare(strict_types=1);

namespace App\Struct\View;

readonly final class PhotoSuggestedSort
{
    private const FIELDS = [
        'remoteStats.views' => '👀 Remote views',
        'remoteStats.favorites' => '⭐ Remote favorites',
        'remoteStats.comments' => '💬 Remote comments',
        'localRanking.views' => '👀 Local views',
        'localRanking.upVotes' => '👍 Local up votes',
        'localRanking.downVotes' => '👎 Local down votes',
        'dateTaken' => '📷 Date taken',
        'dateUploaded' => '☁️ Date uploaded',
        'dateLastRetrieved' => '💾 Date downloaded',
    ];

    private const DIRECTION_ASC = [
        'remoteStats.views' => '↓',
        'remoteStats.favorites' => '↓',
        'remoteStats.comments' => '↓',
        'localRanking.views' => '↓',
        'localRanking.upVotes' => '↓',
        'localRanking.downVotes' => '↓',
        'dateTaken' => 'old to new',
        'dateUploaded' => 'old to new',
        'dateLastRetrieved' => 'old to new',
    ];

    private const DIRECTION_DESC = [
        'remoteStats.views' => '↑',
        'remoteStats.favorites' => '↑',
        'remoteStats.comments' => '↑',
        'localRanking.views' => '↑',
        'localRanking.upVotes' => '↑',
        'localRanking.downVotes' => '↑',
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
