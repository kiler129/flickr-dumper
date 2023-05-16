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
     * Provides the following named capture groups:
     *   id: photo id (always available)
     *   secret: secret for a variant (always available)
     *   size: size code; may not be valid domain-wise (optional, can be either missing or empty string!)
     *   ext: file extension, including leading "." (optional)
     */
    public const VARIANT_FILE_REGEX = '/^(?P<id>[0-9]+)_(?P<secret>[a-z0-9]+)(?:_(?P<size>\d?[a-z]))?(?P<ext>\.\w+)?$/';

    /** @var string|null Secret used to retrieve the photo from API. It is sometimes shared amongst variants/sizes. */
    public readonly string $secret;

    public function __construct(
        /** @var string Platform-wide unique ID of a photo. It seems to be an int-alike (but docs don't guarantee that) */
        public readonly string $photoId,

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

    static public function createFromFilename(string $filename): self
    {
        if (\preg_match(self::VARIANT_FILE_REGEX, $filename, $match) !== 1) {
            throw new DomainException(\sprintf('Filename "%s" cannot be parsed as %s', $filename, self::class));
        }

        return self::createFromComponents($match['id'], $match['secret'], $match['size'] ?: null);
    }

    static public function createFromComponents(string $photoId, string $secret, ?string $sizeCode): self
    {
        $size = $sizeCode === null || $sizeCode === '' ? PhotoSize::defaultSize() : PhotoSize::from($sizeCode);

        return new self($photoId, $secret, $size);
    }
}
