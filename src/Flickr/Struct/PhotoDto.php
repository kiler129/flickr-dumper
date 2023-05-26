<?php
declare(strict_types=1);

namespace App\Flickr\Struct;

use App\Exception\DomainException;
use App\Exception\InvalidArgumentException;
use App\Struct\PhotoExtraFields;
use App\Struct\PhotoSize;

/**
 * Simple DTO to map array response from API to a typed object
 *
 * Below are all the fields discovered. Ones marked with "*" are only available when requested (see PhotoExtraFields).
 *
 * @property-read int $id Identifier of a photo; available: photosets, [probably all]
 * @property-read string $secret Secret used for most CDN sizes; available: photosets,
 * @property-read string $server Server id where photo is; available: photosets,
 * @property-read int $farm Server group id; available: photosets,
 * @property-read string $title
 * @property-read bool $primary
 * @property-read bool $public
 * @property-read bool $friend
 * @property-read bool $family
 * @property-read string $description
 * @property-read array $licenses
 * @property-read \DateTimeInterface $dateUploaded
 * @property-read \DateTimeInterface $dateUpdated
 * @property-read \DateTimeInterface $dateTaken
 * @property-read bool $dateTakenUnknown
 * @property-read string $ownerUsername Username (e.g. "Space X Photos"); available: photosets*,
 * @property-read string $ownerNsid NSID of the owner (e.g. "1234@N01"); available: faves,
 * @property-read string|null $ownerScreenName Nick of the owner (e.g. "spacex"); available: photosets*, faves*
 * @property-read string $iconServer
 * @property-read int $iconFarm
 * @property-read int $views
 * @property-read array $tags
 * @property-read string $originalSecret
 * @property-read string $originalFormat
 *
 * @property-read float $latitude
 * @property-read float $longitude
 * @property-read int $accuracy
 */
final class PhotoDto
{
    private const SIMPLE_TYPECAST_MAP = [
        'id' => 'int',
        'secret' => 'string',
        'server' => 'string',
        'farm' => 'int',
        'title' => 'string',
        'isprimary' => 'bool',
        'ispublic' => 'bool',
        'isfriend' => 'bool',
        'isfamily' => 'bool',
        'datetakenunknown' => 'bool',
        'ownername' => 'string',
        'owner' => 'string',
        'iconserver' => 'string',
        'iconfarm' => 'int',
        'views' => 'int',
        'originalsecret' => 'string',
        'originalformat' => 'string',

        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'int',
    ];
    
    private const KNOWN_TO_API = [
        'primary' => 'isprimary',
        'public' => 'ispublic',
        'friend' => 'isfriend',
        'family' => 'isfamily',
        'licenses' => 'license',
        'dateUploaded' => 'dateupload',
        'dateUpdated' => 'lastupdate',
        'dateTaken' => 'datetaken',
        'dateTakenUnknown' => 'datetakenunknown',
        'ownerUsername' => 'ownername',
        'ownerNsid' => 'owner',
        'ownerScreenName' => 'pathalias',
        'iconServer' => 'iconserver',
        'iconFarm' => 'iconfarm',
        'originalSecret' => 'originalsecret',
        'originalFormat' => 'originalformat',
    ];

    public readonly array $apiData;

    private array $dataTransformed = [];

    public function __set(string $name, mixed $value): void
    {
        $this->apiData[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        if (isset(self::KNOWN_TO_API[$name])) {
            $name = self::KNOWN_TO_API[$name];
        }

        if (isset($this->dataTransformed[$name])) {
            return $this->dataTransformed[$name];
        }

        if (!\array_key_exists($name, $this->apiData)) {
            throw new DomainException('Property ' . $name . ' is not present in the dataset');
        }

        return $this->dataTransformed[$name] = $this->transformValue($name, $this->apiData[$name]);
    }

    public function __isset(string $name): bool
    {
        return \array_key_exists(self::KNOWN_TO_API[$name] ?? $name, $this->apiData);
    }

    /**
     * @return array<string, array{size: PhotoSize, url: string, width: int, height: int}>
     * @deprecated Use PhotoSize and ResolvePhotoVariants as needed
     */
    public function getAvailableSizes(): array
    {
        $ret = [];
        foreach ($this->apiData as $k => $v) {
            if (preg_match('/^url_(.+)$/', $k, $out) !== 1) {
                continue; //not a URL property
            }

            $size = PhotoSize::tryFrom($out[1]);
            if ($size === null) {
                continue;
            }

            $ret[$out[1]] = [
                'size' => $size,
                'url' => $this->apiData[$k],
                'width' => $this->apiData['width_' . $out[1]],
                'height' => $this->apiData['height_' . $out[1]],
            ];
        }

        return $ret;
    }

    /**
     * Same as getAvailableSizes but sorts from smallest to largest size
     * @return array[]
     *
     * @deprecated Use PhotoSize like in getLargestSize.
     */
    public function getSortedSizes(): array
    {
        $sizes = $this->getAvailableSizes();
        \uasort($sizes, fn(array $a, array $b) => ($a['width'] * $a['height']) <=> ($b['width'] * $b['height']));

        return $sizes;
    }

    public function getLargestSize(): ?PhotoSize
    {
        foreach (PhotoSize::CASES_SIZE_DESCENDING as $size)
        {
            $apiField = $size->asApiField();
            if (isset($this->apiData[$apiField])) {
                return $size;
            }
        }

        return null;
    }

    public function hasSizeUrl(PhotoSize $size): bool
    {
        return isset($this->apiData[$size->asApiField()]);
    }

    public function getSizeUrl(PhotoSize $size): ?string
    {
        $field = $size->asApiField();
        if (!isset($this->apiData[$field])) {
            throw new DomainException(\sprintf('Size %s (%s) is not present in the data', $size->name, $size->value));
        }

        return $this->apiData[$field];
    }

    private function transformValue(string $apiName, mixed $value): mixed
    {
        return match ($apiName) {
            //"title" doesn't have "_content" USUALLY but SOMETIMES it does lol
            'title' => $value['_content'] ?? $value,
            PhotoExtraFields::DESCRIPTION->value => $value['_content'] ?? $value,
            PhotoExtraFields::LICENSE->value => \explode(',', $value),
            'dateupload', 'lastupdate', 'datetaken' => $this->castDateTime($value),
            'tags' => is_array($value['tag'] ?? null)
                ? \array_column($value['tag'], '_content')
                : \explode(' ', $value),
            default => $this->transformAutocast($apiName, $value)
        };
    }

    private function transformAutocast(string $apiName, mixed $value): mixed
    {
        if (isset(self::SIMPLE_TYPECAST_MAP[$apiName])) {
            \settype($value, self::SIMPLE_TYPECAST_MAP[$apiName]);
        }

        return $value;
    }

    private function castDateTime(int|string $value): \DateTimeInterface
    {
        if ((string)(int)$value === (string)$value) {
            $dti = new \DateTimeImmutable();
            return $dti->setTimestamp((int)$value);
        }

        return new \DateTimeImmutable($value);
    }

    static public function fromGenericApiResponse(array $fields): self
    {
        $obj = new self();
        $obj->apiData = $fields;

        return $obj;
    }

    static public function fromExtendedApiResponse(array $fields): self
    {
        if (!isset($fields['owner']) || !\is_array($fields['owner']) ||
            !isset($fields['tags']) || !\is_array($fields['tags'])) {
            throw new InvalidArgumentException(
                'It does not look like an extended response (e.g. from flick.photos.getInfo)'
            );
        }

        //we can convert some of the fields ;)
        if (isset($fields['dates']['posted'])) {
            $fields['dateupload'] = $fields['dates']['posted'];
        }
        if (isset($fields['dates']['taken'])) {
            $fields['datetaken'] = $fields['dates']['taken'];
        }
        if (isset($fields['dates']['takenunknown'])) {
            $fields['datetakenunknown'] = $fields['dates']['takenunknown'];
        }
        if (isset($fields['dates']['lastupdate'])) {
            $fields['lastupdate'] = $fields['dates']['lastupdate'];
        }

        if (isset($fields['owner'])) {
            $fields['ownername'] = $fields['owner']['username'];
            $fields['pathalias'] = $fields['owner']['path_alias'];
            $fields['iconserver'] = $fields['owner']['iconserver'];
            $fields['iconfarm'] = $fields['owner']['iconfarm'];
            $fields['owner'] = $fields['owner']['nsid'];
        }
        $fields = \array_merge($fields, $fields['visibility'] ?? []);


        $obj = new self();
        $obj->apiData = $fields;

        return $obj;
    }


}
