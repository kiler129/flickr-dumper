<?php
declare(strict_types=1);

namespace App\Command\App;

use App\Repository\Flickr\PhotoRepository;
use App\UseCase\VerifyImageFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-photos',
    description: 'Verifies all photo records',
)]
class VerifyPhotosCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(private LoggerInterface $log, private PhotoRepository $photoRepo, private VerifyImageFile $verifyImgUC)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('--remove-failed', null, InputOption::VALUE_NONE, 'Automatically remove broken files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Checking all photo records...');

        [$total, $unknownList, $failedList] = $this->verifyAllPhotos();

        $failed = \count($failedList);
        $unknown = \count($unknownList);
        if ($failed === 0 && $unknown === 0) {
            $this->io->success(\sprintf('All %d photos are valid', $total));

            return Command::SUCCESS;
        }

        if ($failed === 0) {
            $this->io->warning(
                \sprintf(
                    'Found %d valid photos, but %d of all %d photos could not be verified',
                    $total - $unknown,
                    $unknown,
                    $total
                )
            );

            $this->io->listing($unknownList);

            return Command::INVALID;
        }

        if ($unknown === 0) {
            $this->io->error(
                \sprintf(
                    'Found %d valid photos, but %d of all %d photos are invalid',
                    $total - $unknown,
                    $failed,
                    $total
                )
            );

            $this->io->listing($unknownList);

            return Command::FAILURE;
        }

        $this->io->error(
            \sprintf(
                'Found %d of %d photos being valid, ' .
                'but there were %d invalid photos and %d photos that could not be verified',
                $total - $unknown,
                $total,
                $failed,
                $unknown
            )
        );

        $this->io->title('Invalid Photos');
        $this->io->listing($failedList);

        if ($input->getOption('remove-failed')) {
            $this->removeFailed($failedList);
        }

        $this->io->title('Photos that could not be verified');
        $this->io->listing($failedList);

        return Command::FAILURE;
    }

    private function verifyAllPhotos(): array
    {
        $toVerify = $this->photoRepo->count([]);
        $this->log->notice("Verifying a total of $toVerify records");

        $verified = 0;
        $unknownList = [];
        $failedList = [];
        foreach ($this->photoRepo->findBy(['status.deleted' => false]) as $photo) {
            if ($verified % 100 === 0) {
                $this->log->notice("Verified $verified of $toVerify records so far");
            }
            ++$verified;

            $path = $photo->getLocalPath();
            if ($path === null) {
                $this->log->warning('Photo id={phid} has no local file path', ['phid' => $photo->getId()]);

                $unknownList[] = $path;
                continue;
            }

            if (!$photo->isFilesystemInSync()) {
                $this->log->warning(
                    'Photo id={phid} image file at {path} is not in sync with the photo record',
                    ['phid' => $photo->getId(), 'path' => $path]
                );
            }

            $status = $this->verifyImgUC->verifyFile($path);
            if ($status === false) {
                $failedList[] = $path;
            } elseif ($status === null) {
                $unknownList[] = $path;
            }
        }

        return [$verified, $unknownList, $failedList];
    }

    private function removeFailed(array $failed): void
    {
        return; //noop for now
    }
}
