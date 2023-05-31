<?php
declare(strict_types=1);

namespace App\Command\Flickr;

use App\Entity\Flickr\Photoset;
use App\Entity\Flickr\UserFavorites;
use App\Repository\Flickr\PhotoRepository;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserFavoritesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'flickr:unlock-index',
    description: 'Unlocks stuck-locked resources in index',
)]
class UnlockIndex extends Command
{
    private SymfonyStyle $io;

    public function __construct(private EntityManagerInterface $om, private PhotoRepository $photoRepo, private PhotosetRepository $photosetRepo, private UserFavoritesRepository $favesRepo) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption('photos', 'p', InputOption::VALUE_NONE, 'Unlocks locked photos')
             ->addOption('albums', 'a', InputOption::VALUE_NONE, 'Unlocks locked photosets/albums')
             ->addOption('favorites', 'f', InputOption::VALUE_NONE, 'Unlocks locked user favorites')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $photos = $input->getOption('albums');
        $photosets = $input->getOption('albums');
        $favorites = $input->getOption('favorites');

        if (!($photos || $photosets || $favorites)) {
            $this->io->warning('Nothing unlocked - see options');

            return Command::FAILURE;
        }

        if (!$this->io->confirm('Do you REALLY want to unlock the index? You should do it only if ' .
                                'something crashed and you are 100% sure that no process is using the index.')) {
            return Command::FAILURE;
        }

        if ($photos) {
            $this->unlockPhotos();
        }

        if ($photosets) {
            $this->unlockPhotosets();
        }

        if ($favorites) {
            $this->unlockFavorites();
        }

        $this->om->flush();

        return Command::SUCCESS;
    }

    private function unlockPhotos(): void
    {
        foreach ($this->photoRepo->findLocked() as $photo) {
            $photo->unlockForWrite(false);
            $this->photoRepo->save($photo);

            $this->io->success(\sprintf('Unlocked photo id=%d', $photo->getId()));
        }
    }

    private function unlockPhotosets(): void
    {
        /** @var Photoset $photoset */
        foreach ($this->photosetRepo->findLocked() as $photoset) {
            $photoset->unlockForWrite();
            $this->photosetRepo->save($photoset);

            $this->io->success(\sprintf('Unlocked photoset/album id=%d', $photoset->getId()));
        }
    }

    private function unlockFavorites(): void
    {
        /** @var UserFavorites $faves */
        foreach ($this->favesRepo->findLocked() as $faves) {
            $faves->unlockForWrite();
            $this->favesRepo->save($faves);

            $this->io->success(\sprintf('Unlocked user favorites for user NSID=%d', $faves->getOwner()->getNsid()));
        }
    }
}
