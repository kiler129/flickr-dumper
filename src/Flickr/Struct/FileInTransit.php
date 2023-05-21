<?php
declare(strict_types=1);

namespace App\Flickr\Struct;

use App\Exception\IOException;
use App\Exception\RuntimeException;

final readonly class FileInTransit
{
    /**
     * @var string It's not guaranteed to exist until file is closed! But it can, so check $closed
     */
    public string $savePath;

    public bool $closed;

    public function __construct(public string $tmpPath, private mixed $handle)
    {
    }

    public function close(): void
    {
        if (isset($this->closed)) {
            throw new RuntimeException('Cannot close file "%s" - use after free');
        }

        \fclose($this->handle);
        $this->closed = true;
    }

    public function write(string $data): int
    {
        if (isset($this->closed)) {
            throw new RuntimeException('Cannot write to file "%s" - use after free');
        }

        $bytes = \fwrite($this->handle, $data);
        if ($bytes === false) {
            throw new  IOException(\sprintf('Write of %d bytes to file "%s" failed', \strlen($data), $this->tmpPath));
        }

        return $bytes;
    }

    static public function open(string $tmpPath, ?string $savePath = null, bool $overwriteExisting = true): self
    {
        $fileMode = $overwriteExisting ? 'ab' : 'xb';
        $handle = \fopen($tmpPath, $fileMode);
        if ($handle === false) {
            throw new IOException(\sprintf('Failed to open file "%s" (mode=%s)', $tmpPath, $fileMode));
        }

        $obj = new self($tmpPath, $handle);

        if ($savePath !== null) {
            $obj->savePath = $savePath;
        }

        return $obj;
    }
}
