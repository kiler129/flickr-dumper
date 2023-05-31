<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class VerifyImageFile
{
    private bool $initialized = false;

    private mixed $magicDb;
    private string|null $jpegInfo;
    private string|null $pngCheck;
    private bool        $jpegGd;
    private bool        $pngGd;
    private bool        $webpGd;
    private bool        $gifGd;

    public function __construct(private LoggerInterface $log = new NullLogger())
    {
    }

    public function verifyFile(string $path): ?bool
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $mimeType = finfo_file($this->magicDb, $path);
        if ($mimeType === false) {
            $this->log->error(
                '🛑 Failed to determine MIME type of {path} - cannot determining if this is a valid image',
                ['path' => $path]
            );

            return null;
        }

        switch ($mimeType) {
            case 'image/jpg':
            case 'image/jpeg':
                if ($this->jpegInfo !== null) {
                    return $this->verifyJpegInfo($path);
                }

                if ($this->jpegGd) {
                    return $this->verifyGd($path, 'imagecreatefromjpeg');
                }

                $this->log->warning('⚠️ No method to verify JPEG file {path} exists', ['path' => $path]);
                return null;

            case 'image/webp':
            case 'image/x-webp':
                if ($this->webpGd) {
                    return $this->verifyGd($path, 'imagecreatefromwebp');
                }

                $this->log->warning('⚠️ No method to verify WebP file {path} exists', ['path' => $path]);
                return null;

            case 'image/png':
            case 'image/x-png':
                if ($this->pngCheck !== null) {
                    return $this->verifyPngCheck($path);
                }

                if ($this->jpegGd) {
                    return $this->verifyGd($path, 'imagecreatefrompng');
                }

                $this->log->warning('⚠️ No method to verify PNG file {path} exists', ['path' => $path]);
                return null;

            case 'image/gif':
                if ($this->gifGd) {
                    return $this->verifyGd($path, 'imagecreatefromgif');
                }

                $this->log->warning('⚠️ No method to verify GIF file {path} exists', ['path' => $path]);
                return null;


            default:
                $this->log->error(
                    '🛑 File {path}, recognized as {mime}, is not a known image file or there is no ' .
                    'method to verify it available, thus it is not valid',
                    ['path' => $path, 'mime' => $mimeType]
                );

                return null;
        }
    }

    private function verifyGd(string $path, string $function): bool
    {
        if (!@$function($path) !== false) {
            $this->log->info('✅ PHP GD2: {path} is valid', ['path' => $path]);

            return true;
        }

        $this->log->error('❌ PHP GD2: {path} is invalid', ['path' => $path]);

        return false;
    }

    private function verifyJpegInfo(string $path): bool
    {
        $process = new Process([$this->jpegInfo, '--json', '--check', $path]);
        $process->setTimeout(10);
        $process->start();
        $process->wait();
        if ($process->isSuccessful()) {
            $this->log->info(
                '✅ JPEGInfo: {path} is valid',
                ['path' => $path, 'msg' => $info['status_detail'] ?? 'unknown error']
            );
            return true;
        }

        try {
            $info = \json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->log->critical('❌ Failed to decode JPEGInfo error output: ' . $e->getMessage());

            return false;
        }

        $this->log->error(
            '❌ JPEGInfo: {path} is invalid: "{msg}"',
            ['path' => $path, 'msg' => $info[0]['status_detail'] ?? 'unknown error']
        );

        return false;
    }
    private function verifyPngCheck(string $path): bool
    {
        $process = new Process([$this->pngCheck, $path]);
        $process->setTimeout(10);
        $process->start();
        $process->wait();
        if ($process->isSuccessful()) {
            $this->log->info(
                '✅ PNGCheck: {path} is valid',
                ['path' => $path, 'msg' => $info['status_detail'] ?? 'unknown error']
            );
            return true;
        }

        $this->log->error(
            '❌ PNGCheck: {path} is invalid: "{msg}"',
            ['path' => $path, 'msg' => \substr(\explode("\n", $process->getOutput())[0], \strlen($path)+2)]
        );

        return false;
    }

    private function initialize(): void
    {
        $this->magicDb = finfo_open(FILEINFO_MIME_TYPE);
        if ($this->magicDb === false) {
            throw new RuntimeException('Failed to load Mime MAGIC database');
        }

        $execFinder = new ExecutableFinder();
        $this->jpegInfo = $execFinder->find('jpeginfo');
        if ($this->jpegInfo === null) {
            $this->jpegGd = \function_exists('imagecreatefromjpeg');
            if ($this->jpegGd) {
                $this->log->error('No "jpeginfo" tool or PHP-GD2/jpg library installed - JPEG validation is impossible');
            } else {
                $this->log->warning('"jpeginfo" not installed - detection of broken JPEGs will slower and inaccurate!');
            }
        }

        $this->pngCheck = $execFinder->find('pngcheck');
        if ($this->pngCheck === null) {
            $this->pngGd = \function_exists('imagecreatefrompng');
            if ($this->pngGd) {
                $this->log->warning('"pngcheck" not installed - detection of broken PNGs will slower and inaccurate!');
            } else {
                $this->log->error('No "pngcheck" tool or PHP-GD2/png library installed - PNG validation is impossible');
            }
        }

        $this->webpGd = \function_exists('imagecreatefromwebp');
        if (!$this->webpGd) {
            $this->log->error('No PHP-GD2/webp library installed - WebP validation is impossible');
        }

        $this->gifGd = \function_exists('imagecreatefromgif');
        if (!$this->gifGd) {
            $this->log->error('No PHP-GD2/gif library installed - GIF validation is impossible');
        }
    }
}
