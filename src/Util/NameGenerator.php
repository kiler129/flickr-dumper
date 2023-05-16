<?php
declare(strict_types=1);

namespace App\Util;

class NameGenerator
{
    public const PHOTOSET_DIR_ID_EXTRACT_REGEX = '/#(.*)#/';
    public const PHOTOSET_BLACKLIST_REGEX = '/\/-#(.*)#$/';


    public function getDirectoryNameForPhotoset(array $photoset): string
    {
        $photosetId = $photoset['id'];
        $title = $photoset['title']['_content'] ?? 'Photoset';

        $idLen = \strlen($photosetId);
        $titleLen = \strlen($photosetId);
        $formatLen = 5 + $idLen; //5 chars for formatting below

        if ($formatLen + $titleLen > 255) {
            $title = \substr($title, 0, 255 - $formatLen);
        }

        return \sprintf('%s (#%s#)', $title, $photosetId);
    }
}
