<?php
declare(strict_types=1);

namespace App\Flickr\Enum;

/**
 * Single place for all collection types to be defined
 *
 * See \App\Flickr\Url\UrlParser for extensive documentation as everything we know is really derived from URLs.
 *
 * Checklist for adding a new type:
 *  - Add new "case"
 *  - Ensure plural forms
 *  - Ensure regex lists here
 *  - Add new Flickr\Struct\Identity\*Identity
 *  - Make sure UrlParser supports creation of new identity
 *  - Check SyncCollectionCommand (works with --type)
 *  - Update \App\Flickr\UseCase\SyncCollection to add new type
 *  - Search for usages of one of the cases :D
 */
enum MediaCollectionType: string
{
    case USER_PHOTOSTREAM = 'user'; //not a real type-type, just lack of any type for the owner
    case USER_FAVES = 'faves';
    case ALBUM = 'album';
    case GALLERY = 'gallery';
    case POOL = 'pool';

    //These forms are using in collection view
    //This belongs more to URLs but is here to ensure it gets updated when new type is added
    public const CASES_PLURAL = [
        self::USER_PHOTOSTREAM->value => '', //technically speaking it's only plural as it has no singular URLs
        self::USER_FAVES->value => 'favorites',
        self::ALBUM->value => 'albums',
        self::GALLERY->value => 'galleries',
        //self::POOL->value => pools/groups aren't owned by a user so they have no plural form
    ];



    public const CASES_REGEX_LIST =
        self::USER_FAVES->value . '|' .
        self::ALBUM->value . '|' .
        self::GALLERY->value . '|' .
        self::POOL->value
    ;

    public const CASES_PLURAL_REGEX_LIST =
        self::CASES_PLURAL[self::USER_FAVES->value] . '|' .
        self::CASES_PLURAL[self::ALBUM->value] . '|' .
        self::CASES_PLURAL[self::GALLERY->value] //. '|' .
        //self::CASES_PLURAL[self::POOL->value] => groups have a completely different link
    ;

    /**
     * @return non-empty-list<string>
     */
    public static function valuesAsList(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[] = $case->value;
        }

        return $out;
    }

    public static function tryFromPlural(string $type): ?self
    {
        $singular = \array_search($type, self::CASES_PLURAL, true);
        if ($singular === false) {
            return null;
        }

        return self::from($singular);
    }

    public static function fromPlural(string $type): self
    {
        $singular = \array_search($type, self::CASES_PLURAL, true);
        if ($singular === false) {
            throw new \ValueError(
                \sprintf(
                    'Cannot create %s from plural type "%s". Value plural types are: %s',
                    self::class,
                    $type,
                    \implode('", "', self::CASES_PLURAL)
                )
            );
        }

        return self::from($singular);
    }
}
