<?php
declare(strict_types=1);

namespace App\Struct;

final class PhotoExtraFields
{
    public const LICENSE         = 'license';
    public const DATE_UPLOAD     = 'date_upload';
    public const DATE_TAKEN      = 'date_taken';
    public const OWNER_NAME      = 'owner_name';
    public const ICON_SERVER     = 'icon_server';
    public const ORIGINAL_FORMAT = 'original_format';
    public const LAST_UPDATE     = 'last_update';
    public const GEO             = 'geo';
    public const TAGS            = 'tags';
    public const MACHINE_TAGS    = 'machine_tags';
    public const ORG_DIMENSIONS  = 'o_dims';
    public const VIEWS           = 'views';
    public const MEDIA           = 'media';
    public const PATH_ALIAS      = 'path_alias';
    public const URL_SQ          = 'url_sq';
    public const URL_THUMB_100   = 'url_t';
    public const URL_THUMB_75    = 'url_s';
    public const URL_THUMB_240   = 'url_m';
    public const URL_ORIGINAL    = 'url_o';

    public const SIZE_URL_MAP = [
        PhotoSize::THUMB_75 => self::URL_THUMB_75,
        PhotoSize::THUMB_100 => self::URL_THUMB_100,
        PhotoSize::SMALL_240 => self::URL_THUMB_240,
        PhotoSize::ORIGINAL => self::URL_ORIGINAL
    ];

    public const ALL = [
        self::LICENSE,
        self::DATE_UPLOAD,
        self::DATE_TAKEN,
        self::OWNER_NAME,
        self::ICON_SERVER,
        self::ORIGINAL_FORMAT,
        self::LAST_UPDATE,
        self::GEO,
        self::TAGS,
        self::MACHINE_TAGS,
        self::ORG_DIMENSIONS,
        self::VIEWS,
        self::MEDIA,
        self::PATH_ALIAS,
        self::URL_SQ,
        self::URL_THUMB_100,
        self::URL_THUMB_75,
        self::URL_THUMB_240,
        self::URL_ORIGINAL,
    ];
}
