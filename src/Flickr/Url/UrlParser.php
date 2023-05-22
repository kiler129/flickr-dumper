<?php
declare(strict_types=1);

namespace App\Flickr\Url;

use App\Exception\Flickr\InvalidUrlException;
use App\Exception\LogicException;
use App\Flickr\Enum\MediaCollectionType;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\Identity\GalleryIdentity;
use App\Flickr\Struct\Identity\MediaIdentity;
use App\Flickr\Struct\Identity\PoolIdentity;
use App\Flickr\Struct\Identity\UserFavesIdentity;
use App\Flickr\Struct\Identity\UserPhotostreamIdentity;

final class UrlParser
{
    private const BASE_URI_REGEX_CHUNK = 'https?://(?:[\w\-]+\.)?flickr\.com';

    /**
     * Matches direct link to a photo viewer. Optionally the photo can present in a context/container (see below)
     *
     * Available named groups:
     *   owner: user name (e.g. "spacex") or id (e.g. 1234567@N02) of the user (mandatory)
     *   photoId: id of the photo (mandatory)
     *   colType: type of the context photo is in (optional)
     *   colOwner: user name (e.g. "spacex") or id (e.g. 1234567@N02) of the user owning a given collection (optional,
     *             only present with some colType types)
     *   colId: id of the context defined by colType (optional; always present when colType is present)
     *
     * WARNING about "owner" values:
     *   First terminology:
     *     - NSID: unique id of user, e.g. 130608600@N05
     *     - screenname: unique and optional "nick" of the user, used in URLs (e.g. "spacex")
     *     - username: non-unique display name of user (e.g. "Official SpaceX Photos")
     *
     *   So we need to be EXTRA sure that what "owner" put in identity actually is (NSID vs. screenname).
     *   Trying to use screenname as NSID in API calls at best will produce "User not found" API error, but may be worse,
     *   actually. Why? Because on Flickr you can name yourself "124538397@N02". When you do it, and such an ID exists
     *   for other user, your ID will not be used anywhere in URLs (as Flickr first checks if "owner" in url matches
     *   IDs). If you put it there it will go to the legitimate user with that ID. ...and this is why trying to use
     *   screenname blindly as NSID can result in another user matching and we will probably get something confusing but
     *   harmless like "Album not found".... but what if you decide to get favs....
     *
     *
     * The following collections/contexts are known:
     *   <empty>: if empty or not present it designates user's photo as uploaded
     *   faves: favorite set of some user; id is the favorites list owner screenname or NSID
     *   album: album/photoset; id is the album id
     *   gallery: public gallery; id is the unique gallery id, colOwner seems to also always be present
     *   pool: public group of photos; id is group nickname or id
     *
     *
     * Example URLs:
     *   https://www.flickr.com/photos/flickr/52834970958/                                                  regular uploaded photo
     *   https://www.flickr.com/photos/krishnacolor/52789698833/in/faves-66956608@N06/                      user kris... photo id 527... faved by 66...
     *   https://www.flickr.com/photos/flickr/50514594387/in/album-72157716823987722/                       photo in user's own album
     *   https://www.flickr.com/photos/flickr/50514594387/in/gallery-130652955@N08-72157673765086717/       photo 505... of user flickr in gallery 721... owned by 130...
     *   https://www.flickr.com/photos/kedleson/52307221457/in/pool-52240293230@N01                         photo 523... of user ked... in pool 522...
     */
    public const PHOTO_VIEW_URL_REGEX =
        '/' .
            self::BASE_URI_REGEX_CHUNK .
            'photos\/' .
            '(?P<owner>[^\/]+)\/' .
            '(?P<photoId>\d+)' .
            '(?:\/in\/' .
                '(?P<colType>(?:' . MediaCollectionType::CASES_REGEX_LIST . '))' .
        '(?:-(?P<colOwner>[^-]+))?' . //owner is only present sometimes
                '-(?P<colId>[^\/]+)' . //if "/in/" part exists it always has colType and colId
            ')?' . //the "/in/"-something is optional
        '/i';

    /**
     * Matches direct link to an album (photoset), gallery, group, favorites list etc etc.
     *
     * @todo this can fail in some cases if a link with page number is specified (i.e. it will treat "page3" as id)
     *
     * Available named groups:
     *   owner: user name (e.g. "spacex") or id (e.g. 1234567@N02) of the user (mandatory)
     *   colTypePlural: type of the collection (optional, see below)
     *   colId: id of the collection (optional for some types)
     *
     * The following collection types are known:
     *  <empty>: user's photostream; naturally it has no id (if the url has ID after slash it's a photo view link)
     *  albums: album/photoset; colId present when linked to single album (no id = list of user albums)
     *  favorites: user's favorites; colId never present (user can only have one favorite's list)
     *  galleries: a gallery curated by a user
     *
     *  WARNING about "owner" values:
     *    <<< see section in PHOTO_VIEW_URL_REGEX >>>
     *
     * Example URLs:
     *   https://www.flickr.com/photos/flickr/                                      user's photostream (all photos)
     *   https://www.flickr.com/photos/spacex/albums/72157667519938826              album with screenname owner
     *   https://www.flickr.com/photos/spacex/albums                                albums of a user with screenname
     *   https://www.flickr.com/photos/124538397@N02/albums/72157648850853412       album with [possibly] NSID owner
     *   https://www.flickr.com/photos/finoucat/albums/72157681758092906/page2      album with page designation
     *   https://www.flickr.com/photos/flickr/favorites                             favorites of a user w/screenname
     *   https://www.flickr.com/photos/136314743@N04/favorites                      favorites of a user w/[maybe] NSID
     *   https://www.flickr.com/photos/flickr/galleries                             galleries of a user screenname
     *   https://www.flickr.com/photos/flickr/galleries/72157721619096849/          gallery of a user screenname
     */
    public const COLLECTION_VIEW_URL_REGEX =
        '/' .
            self::BASE_URI_REGEX_CHUNK .
            'photos\/' .
            '(?P<owner>[^\/]+)' .
            '(?:\/' .
                '(?P<colTypePlural>(?:' . MediaCollectionType::CASES_PLURAL_REGEX_LIST . '))' .
                '(?:\/(?P<colId>[^\/$]+))?' . //colId may be empty even if type is present!
            ')?' . //the whole type+id is optional
        '/i';


    public const URL_REGEX =
        '#^' .
            'https?://(?:[\w\-]+\.)?flickr\.com' .
            '/(?:(?P<type>photos)/(?P<user>[^/]+)' . //some user photos
                '(?:' . //picking between mediaID and colTypePlural and nothing (i.e. user's photostream/all photos)
                        '(?:/(?P<mediaId>\d+)' . //single photo, e.g. "123" in "/photos/123"
                            '(?:/in/(?:' . //picking between gallery and non-gallery types
                                    '(?:(?P<colType>' . MediaCollectionType::GALLERY->value . ')' .//collection w/owner AND id
                                        '(?:-(?P<colOwner>[^-]+))?' . //owner is only present when collection can be owner by others
                                        '-(?P<colId>[^/]+)' .
                                    ')' . //end of colType=gallery
                                '|' .
                                    '(?:(?P<colType>(?:' . MediaCollectionType::ALBUM->value . '))' .//self-owned collection(s)
                                        '-(?P<colId>[^/]+)' .
                                    ')' .
                                '|' .
                                    '(?:(?P<colType>(?:' . MediaCollectionType::USER_FAVES->value . '|' . MediaCollectionType::POOL->value . '))' .
                                        '-(?P<colOwner>[^/]+)' .
                                    ')' .
                            '))?' . // end of /in/<colType>, that is optional
                        ')' . //end of photo/video view with id
                    '|' . //single photo view vs. collection(s) view
                        //e.g. "albums" in "/photos/flickr/albums"
                        '(?:/(?P<colTypePlural>(?:' . MediaCollectionType::CASES_PLURAL_REGEX_LIST . '))' .
                             '(?:/(?P<colId>\d+))?' . //single album, e.g. album 123 in "/photos/flickr/albums/123"
                            '(?:/page(?P<page>\d+))?' . //pagination for photos in collection or multiple collections
                        ')' . //end of collection colTypePlural (if not present it means user photostream or a single photo)
                    '|' . //collection(s) view vs nothing (i.e. photostream)
                    '' . //photostream => it will be empty [left for clarity of intentions]
                ')' .

            ')|' . //end of /<type>photos/<user>
            '/(?:(?P<type>groups)/(?P<colOwner>[^/]+))(?:/pool)?(?:/page(?P<page>\d+))?|' .
            '/(?:(?P<type>search)(?:/\?(?P<searchQuery>.+))?)' .
        '/?#Ji';

    //public function getPhotosetIdentity(string $url): AlbumIdentity
    //{
    //    //Passed link to a collection of photos directly
    //    $col = $this->parseCollectionUrl($url);
    //    if ($col !== null) {
    //        if ($col['colTypePlural'] ?? '' !== 'albums') {
    //            throw new InvalidUrlException(
    //                \sprintf(
    //                    'Url "%s" is a link to a collection of photos, but it is not a photoset/album (found "%s")',
    //                    $url,
    //                    $col['colTypePlural'] ?? '*user photostream*'
    //                )
    //            );
    //        }
    //
    //        if ($col['colId'] ?? '' === '') {
    //            throw new InvalidUrlException(
    //                \sprintf('Url "%s" is a link to a list of albums and not a singular album', $url)
    //            );
    //        }
    //
    //        return new AlbumIdentity($col['colId'], $col['owner']);
    //    }
    //
    //    //Passed a link to a single photo that happens to have a context of the collection
    //    $view = $this->parsePhotoViewUrl($url);
    //    if ($view === null) {
    //        throw new InvalidUrlException('URL is neither a link to a photoset/album nor a photo in an album');
    //    }
    //
    //    if ($view['colType'] ?? '' !== 'album') {
    //        throw new InvalidUrlException(
    //            \sprintf(
    //                'Url "%s" is a link to a photo in a collection, but it is not a photoset/album (found "%s")',
    //                $url,
    //                $col['colTypePlural'] ?? '*user photostream*'
    //            )
    //        );
    //    }
    //
    //    //No need to check if colId is present as the regex guarantees that if type is there the type is too
    //    //Links with type and no id aren't valid
    //    return new AlbumIdentity($col['colId'], $col['owner']);
    //}

    //public function getCollectionIdentity(string $url): CollectionIdentity
    //{
    //    //Collection URL parsing is more permissive and can return e.g. user photostream for view URL, so it should be
    //    // checked second.... screw that: this is actually wrong
    //    //In this one we should prioritize COLLECTIONS... so we need two separate regexes for that but one should
    //    //match STRICTLY collections and never match individual photos really
    //
    //    $data = $this->parsePhotoViewUrl($url);
    //    if ($data !== null && $data['colType'] ?? '' !== '') {
    //        $type = CollectionType::from($data['colType']);
    //    } else {
    //        $data = $this->parseCollectionUrl($url);
    //        if ($data === null) {
    //            throw new InvalidUrlException('URL is neither a link to a collection nor a photo in a collection');
    //        }
    //
    //        $type = CollectionType::fromPlural($col['colTypePlural'] ?? '');
    //    }
    //
    //    //If you get a DomainException here it's possible that a URL to a list of albums was passed instead of e.g.
    //    // single album
    //    return match ($type) {
    //        CollectionType::USER_PHOTOSTREAM => new UserPhotostreamIdentity($data['owner']),
    //        CollectionType::USER_FAVES => new UserFavesIdentity($data['owner']),
    //        CollectionType::ALBUM => new AlbumIdentity($data['owner'], $data['colId']),
    //        CollectionType::GALLERY => new AlbumIdentity($data['owner'], $data['colId']),
    //        //pool handling is unknown
    //    };
    //}
    public function getMediaCollectionIdentity(string $url): MediaCollectionIdentity|null
    {
        if (\preg_match(UrlParser::URL_REGEX, $url, $result, \PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        if ($result['type'] === 'groups') {
            return new PoolIdentity($result['colOwner']);
        }

        if ($result['type'] !== 'photos') {
            return null;
        }

        //No 2nd-level collection type => user photostream
        if ($result['colType'] === null && $result['colTypePlural'] === null) {
            return new UserPhotostreamIdentity($result['user']);
        }

        //We should never have both. If we do it's a broken regex.
        \assert($result['colType'] === null || $result['colTypePlural'] === null);

        $isPlural = $result['colTypePlural'] !== null;
        try {
            $colType = $isPlural
                ? MediaCollectionType::fromPlural($result['colTypePlural'])
                : MediaCollectionType::from($result['colType']);
        } catch (\ValueError $e) {
            throw new InvalidUrlException(
                \sprintf(
                    'Invalid %s collection type "%s"',
                    $isPlural ? 'plural' : 'singular',
                    $isPlural ? $result['colTypePlural'] : $result['colType']
                ), $e->getCode(), $e
            );
        }

        try {
            return match ($colType) {
                MediaCollectionType::ALBUM => $result['colId'] === null
                    ? null  //this would be a collection of albums
                    : new AlbumIdentity($result['user'], $result['colId']),
                MediaCollectionType::USER_FAVES => new UserFavesIdentity($result['user']),
                MediaCollectionType::GALLERY => $result['colId'] === null
                    ? null //this would be a collection of galleries
                    : new GalleryIdentity($result['colOwner'] ?? $result['user'], $result['colId']),
                MediaCollectionType::POOL => new PoolIdentity($result['colOwner']),
            };
        } catch(\Throwable $e) {
            throw new InvalidUrlException(
                \sprintf(
                    'Cannot create identity from URL "%s": %s',
                    $url,
                    $e->getMessage()
                ), $e->getCode(), $e
            );
        }
    }

    public function getMediaIdentity(string $url): MediaIdentity|null
    {
        if (\preg_match(UrlParser::URL_REGEX, $url, $result, \PREG_UNMATCHED_AS_NULL) !== 1 ||
            $result['type'] !== 'photos' || $result['mediaId'] === null) {
            return null;
        }

        return new MediaIdentity($result['user'], (int)$result['mediaId']);
    }

    /**
     * Gets filename from CDN URL
     *
     * Warning: this method doesn't perform any verification, so make sure you're passing something sensible!
     *
     * @return string
     */
    public function getStaticFilename(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH);
        if ($path === false) {
            throw new InvalidUrlException(\sprintf('The URL "%s" is invalid', $url));
        }

        return basename($path);
    }

    public function isWebUrl(string $url): bool
    {
        $regex = '#^' . self::BASE_URI_REGEX_CHUNK . '#i';
        return \preg_match($regex, $url) === 1;
    }

    private function parseCollectionUrl(string $url): ?array
    {
        if (\preg_match(self::COLLECTION_VIEW_URL_REGEX, $url, $match) !== 1) {
            return null;
        }

        return $match;
    }

    private function parsePhotoViewUrl(string $url): ?array
    {
        if (\preg_match(self::PHOTO_VIEW_URL_REGEX, $url, $match) !== 1) {
            return null;
        }

        return $match;
    }
}
