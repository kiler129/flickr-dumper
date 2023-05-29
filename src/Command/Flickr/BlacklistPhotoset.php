<?php
declare(strict_types=1);

namespace App\Command\Flickr;

use App\Entity\Flickr\Photoset;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Url\UrlParser;
use App\Repository\Flickr\PhotosetRepository;
use App\UseCase\ResolveOwner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'flickr:blacklist-photoset',
    aliases: [
        'flickr:blacklist-album',
        'flickr:block-photoset',
        'flickr:block-album',
    ],
    description: 'Marks albums as blacklisted',
)]
class BlacklistPhotoset extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private LoggerInterface $log,
        private PhotosetRepository $photosetRepo,
        private UrlParser $urlParser,
        private ResolveOwner $resolveOwner
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
             ->addArgument(
                 'photosets',
                 InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                 'One or more phototset/albums to blacklist (URLs or files)'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $total = 0;
        $count = 0;
        foreach($input->getArgument('photosets') as $photoset) {
            $fromFile = $this->blacklistFromFile($photoset);
            if ($fromFile !== null) {
                $total += $fromFile[0];
                $count += $fromFile[1];
                continue;
            }

            ++$total;
            $count += $this->blacklistPhotoset($photoset);
        }

        if ($count === $total) {
            $this->io->success(\sprintf('Successfully blacklisted all %d photosets', $count));

            return Command::SUCCESS;
        }


        $this->io->warning(\sprintf('Blacklisted %d photosets; %d out of %d failed', $total, $total-$count, $total));

        return Command::FAILURE;
    }

    /**
     * @return array{0: int, 1: int}|null [total,successful] or null if unable to read
     */
    private function blacklistFromFile(string $filePath): array|null
    {
        if (!\file_exists($filePath)) {
            $this->log->debug('"{in}" is not an existing file - assuming URL', ['in' => $filePath]);
            return null;
        }

        $this->log->info('"{in}" looks like a file - reading blacklist URLs from it', ['in' => $filePath]);
        $lines = \file($filePath, \FILE_IGNORE_NEW_LINES|\FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->io->error('Failed to open blacklist file at ' . $filePath);
        }

        $total = $ok = 0;
        foreach ($lines as $line) {
            ++$total;
            $ok += $this->blacklistPhotoset($line);
        }

        return [$total, $ok];
    }

    private function blacklistPhotoset(string $photoset): bool
    {
        $identity = $this->urlParser->getMediaCollectionIdentity($photoset);

        if ($identity === null) {
            $this->io->error(\sprintf('Url "%s" doesn\'t look like an album URL nor any other collection', $photoset));

            return false;
        }

        if (!($identity instanceof AlbumIdentity)) {
            $this->io->error(
                \sprintf(
                    'Url "%s" is not a photoset URL (found %s)',
                    $photoset,
                    (new \ReflectionClass($identity))->getShortName()
                )
            );

            return false;
        }

        $user = $this->resolveOwner->lookupUserByPathAlias($identity->owner);
        $photoset = $this->photosetRepo->find((int)$identity->setId);
        if ($photoset === null) {
            $this->log->warning('Photoset {id} does not exist - creating blacklisted shell', ['id' => $identity->setId]
            );
            $photoset = new Photoset((int)$identity->setId, $user, new \DateTimeImmutable('1970-01-01 00:00:00.0'));
            $photoset->setTitle('Blacklist shell @ ' . \date('Y-m-d H:i:s'));
            $photoset->setDeleted();

        } elseif ($photoset->isBlacklisted()) {
            $this->log->warning('Photoset {id} already blacklisted - skipping', ['id' => $identity->setId]);

            return true;
        } else {
            $this->log->debug('Photoset {id} already exists - no need to create shell', ['id' => $identity->setId]);
        }

        $photoset->setBlacklisted();
        $this->photosetRepo->save($photoset, true);
        $this->log->notice('Photoset {id} blacklisted successfully', ['id' => $identity->setId]);

        return true;
    }
}
