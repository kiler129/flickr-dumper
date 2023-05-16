<?php
declare(strict_types=1);

namespace App\Struct;

use App\Exception\DomainException;

/**
 * @property-read string $id
 * @property-read string $secret
 * @property-read string $server
 * @property-read int $farm
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
 * @property-read string $ownerName
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
final class PhotoMetadata
{
    private const SIMPLE_TYPECAST_MAP = [
        'id' => 'string',
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
        'ownerName' => 'ownername',
        'iconServer' => 'iconserver',
        'iconFarm' => 'iconfarm',
        'originalSecret' => 'originalsecret',
        'originalFormat' => 'originalformat',
    ];

    private array $data = [];

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        if (isset(self::KNOWN_TO_API[$name])) {
            $name = self::KNOWN_TO_API[$name];
        }

        if (!\array_key_exists($name, $this->data)) {
            throw new DomainException('Property ' . $name . ' is not present in the dataset');
        }


        return $this->transformValue($name, $this->data[$name]);
    }

    public function __isset(string $name): bool
    {
        if (isset(self::KNOWN_TO_API[$name])) {
            $name = self::KNOWN_TO_API[$name];
        }

        return \array_key_exists($name, $this->data);
    }

    /**
     * @return array<string, array{size: PhotoSize, url: string, width: int, height: int}>
     */
    public function getAvailableSizes(): array
    {
        $ret = [];
        foreach ($this->data as $k => $v) {
            if (preg_match('/^url_(.+)$/', $k, $out) !== 1) {
                continue; //not a URL property
            }

            $size = PhotoSize::tryFrom($out[1]);
            if ($size === null) {
                continue;
            }

            $ret[$out[1]] = [
                'size' => $size,
                'url' => $this->data[$k],
                'width' => $this->data['width_' . $out[1]],
                'height' => $this->data['height_' . $out[1]],
            ];
        }

        return $ret;
    }

    /**
     * Same as getAvailableSizes but sorts from smallest to largest size
     * @return array[]
     */
    public function getSortedSizes(): array
    {
        $sizes = $this->getAvailableSizes();
        \uasort($sizes, fn(array $a, array $b) => ($a['width'] * $a['height']) <=> ($b['width'] * $b['height']));

        return $sizes;
    }

    private function transformValue(string $apiName, mixed $value): mixed
    {
        return match ($apiName) {
            PhotoExtraFields::DESCRIPTION->value => $value['_content'] ?? null,
            PhotoExtraFields::LICENSE->value => \explode(',', $value),
            'dateupload', 'lastupdate', 'datetaken' => $this->castDateTime($value),
            'datetakenunknown' => $this->castPseudoBool($value),
            'tags' => \explode(' ', $value),
            default => $this->autoCastToKnownType($apiName, $value)
        };
    }

    private function castPseudoBool(int|string $value): bool
    {
        return $value === 1 || $value === '1';
    }

    private function castDateTime(int|string $value): \DateTimeInterface
    {
        if ((string)(int)$value === (string)$value) {
            $dti = new \DateTimeImmutable();
            return $dti->setTimestamp((int)$value);
        }


        return new \DateTimeImmutable($value);
    }

    private function autoCastToKnownType(string $apiName, mixed $value): mixed
    {
        if (isset(self::SIMPLE_TYPECAST_MAP[$apiName])) {
            \settype($value, self::SIMPLE_TYPECAST_MAP[$apiName]);
        }

        return $value;
    }

    static public function fromApiResponse(array $fields): self
    {
        $obj = new self();
        $obj->data = $fields;

        return $obj;
    }
}
