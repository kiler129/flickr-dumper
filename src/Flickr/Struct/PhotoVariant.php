<?php
declare(strict_types=1);

namespace App\Flickr\Struct;

use App\Exception\DomainException;
use App\Struct\PhotoSize;

/**
 * A basic unique photo version identity
 */
final class PhotoVariant
{
    /**
     * Matches exactly a file downloaded from Flickr. See FROM_FILE_REGEX for details.
     */
    public const VARIANT_FILE_REGEX = '/^' . self::FROM_FILE_REGEX .  '/';

    /**
     * Matches a file that contains ID & secret and was downloaded from Flickr, and potentially renamed.
     * See FROM_FILE_REGEX for details.
     */
    public const FUZZY_EXTRACT_FROM_FILE = '/^' . self::FROM_FILE_REGEX .  '/';

    /**
     * Provides the following named capture groups:
     *   id: photo id (always available)
     *   secret: secret for a variant (always available)
     *   size: size code; may not be valid domain-wise (optional, can be either missing or empty string!)
     *   ext: file extension, including leading "." (optional)
     *
     * This is a part of a regex - it can either be anhored to match full file name (like in VARIANT_FILE_REGEX) or
     * used for fuzzy-matching to find something that looks like id in a filename.
     */
    private const FROM_FILE_REGEX = '(?P<id>[0-9]+)_(?P<secret>[a-z0-9]+)(?:_(?P<size>\d?[a-z]))?(.+)?(?P<ext>\.\w+)$';


    /** @var string|null Secret used to retrieve the photo from API. It is sometimes shared amongst variants/sizes. */
    public readonly string $secret;

    public function __construct(
        /** @var int Platform-wide unique ID of a photo. It seems to be an int-alike (but docs don't guarantee that) */
        public readonly int $photoId,

        string $secret,

        /** @var PhotoSize Size of this variant */
        public readonly PhotoSize $size,
    ) {
        $this->secret = \strtolower($secret);
    }

    public function asBasename(): string
    {
        $name = $this->photoId . '_' . $this->secret;

        $size = $this->size->value;
        if ($size !== '') {
            $name .= '_' . $size;
        }

        return $name;
    }

    static public function createFromFilename(string $filename, bool $fuzzy = false): self
    {
        $regex = $fuzzy ? self::FUZZY_EXTRACT_FROM_FILE : self::VARIANT_FILE_REGEX;
        if (\preg_match($regex, $filename, $match) !== 1) {
            throw new DomainException(\sprintf('Filename "%s" cannot be parsed as %s', $filename, self::class));
        }

        return self::createFromComponents((int)$match['id'], $match['secret'], $match['size'] ?: null);
    }

    static public function createFromComponents(int $photoId, string $secret, ?string $sizeCode): self
    {
        $size = $sizeCode === null || $sizeCode === '' ? PhotoSize::defaultSize() : PhotoSize::from($sizeCode);

        return new self($photoId, $secret, $size);
    }
}
