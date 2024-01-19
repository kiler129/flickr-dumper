<?php
declare(strict_types=1);

namespace App\Command\Flickr;

use App\Entity\Flickr\Collection\Gallery;
use App\Entity\Flickr\Collection\Photoset;
use App\Entity\Flickr\Photo;
use App\Entity\Flickr\Syncable;
use App\Entity\Flickr\User;
use App\Entity\Flickr\UserFavorites;
use App\Repository\Flickr\GalleryRepository;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'flickr:unlock-index',
    description: 'Unlocks stuck-locked resources in index. Run without options to see locked entities.',
)]
class UnlockIndex extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $om,
        private PhotoRepository $photoRepo,
        private PhotosetRepository $photosetRepo,
        private UserFavoritesRepository $favesRepo,
        private GalleryRepository $galleryRepo,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption('all', 'A', InputOption::VALUE_NONE, 'Unlocks all entities')
             ->addOption('photos', 'p', InputOption::VALUE_NONE, 'Unlocks locked photos')
             ->addOption('albums', 'a', InputOption::VALUE_NONE, 'Unlocks locked photosets/albums')
             ->addOption('favorites', 'f', InputOption::VALUE_NONE, 'Unlocks locked user favorites')
             ->addOption('galleries', 'g', InputOption::VALUE_NONE, 'Unlocks locked user galleries')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $all = $input->getOption('all');
        $photos = $input->getOption('photos');
        $photosets = $input->getOption('albums');
        $favorites = $input->getOption('favorites');
        $galleries = $input->getOption('galleries');

        if (!($all || $photos || $photosets || $favorites || $galleries)) {
            $this->listLocks();
            return Command::FAILURE;
        }

        if (!$this->io->confirm('Do you REALLY want to unlock the index? You should do it only if ' .
                                'something crashed and you are 100% sure that no process is using the index.')) {
            return Command::FAILURE;
        }

        if ($photos || $all) {
            $this->unlockPhotos();
        }

        if ($photosets || $all) {
            $this->unlockPhotosets();
        }

        if ($favorites || $all) {
            $this->unlockFavorites();
        }

        if ($galleries || $all) {
            $this->unlockGalleries();
        }

        $this->om->flush();

        return Command::SUCCESS;
    }

    private function listLocks()
    {
        $this->printLocksTable(
            $this->photoRepo->findLocked(),
            $this->photosetRepo->findLocked(),
            $this->favesRepo->findLocked(),
            $this->galleryRepo->findLocked(),
        );
    }

    /**
     * @param array<string, string>  $rows
     */
    private function printLocksTable(iterable ...$collections): void
    {
        $formatDate = fn (\DateTimeInterface|null $dtime) => $dtime === null ? 'Never' : $dtime->format('Y-m-d H:i:s');
        $entityLink = function(string|int|float $name, string $type, string|int $id) {
            $hyperlink = fn(string $route, string $paramName) => \sprintf(
                '<href=%s>%s</>',
                $this->urlGenerator->generate($route, [$paramName => $id], UrlGeneratorInterface::ABSOLUTE_URL),
                $name
            );

            //This cannot be a simple match() lookup as $type may be a Doctrine proxy object
            foreach ([
                         User::class => ['app.user_resources', 'userId'],
                         Photo::class => ['app.photo_file_bin', 'photoId'],
                         Photoset::class => ['app.albums_redirect_to_user_album', 'albumId'],
                         UserFavorites::class => ['app.user_resources_favorites', 'userId'],
                         Gallery::class => ['app.galleries_redirect_to_user_gallery', 'galleryId'],
                     ] as $baseClass => $routeParams) {
                if ($type === $baseClass || \is_subclass_of($type, $baseClass)) {
                    return $hyperlink(...$routeParams);
                }
            }

            return $name . '-' . $type;
        };

        $createRow = fn(object $entity) => [
            'Type' => (new \ReflectionObject($entity))->getShortName(),
            'ID' => ($entity instanceof UserFavorites) ? 'N/A (favorites)' :
                $entityLink(
                $entity->getId(),
                $entity::class,
                $entity->getId()
            ),
            'Title' => ($entity instanceof UserFavorites) ? 'N/A' : $entity->getTitle(),
            'Owner' => $entityLink(
                $entity->getOwner()->getDisplayableShortName(),
                $entity->getOwner()::class,
                $entity->getOwner()->getNsid(),
            ),
            'Last Retrieved/Synced' => $formatDate(
                ($entity instanceof Syncable) ? $entity->getDateSyncCompleted() : $entity->getDateLastRetrieved()
            ),
            'Locked at' => $entity->getWriteLockTimestamp()->format('Y-m-d H:i:s'),
        ];

        $rows = [];
        foreach ($collections as $collection) {
            foreach ($collection as $entity) {
                $rows[] = $createRow($entity);
            }
        }

        if (\count($rows) === 0) {
            $this->io->success('No locked entities found');
            return;
        }

        $this->io->table(\array_keys($rows[\array_key_first($rows)]), $rows);
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

    private function unlockGalleries(): void
    {
        /** @var Gallery $gallery */
        foreach ($this->galleryRepo->findLocked() as $gallery) {
            $gallery->unlockForWrite();
            $this->galleryRepo->save($gallery);

            $this->io->success(\sprintf('Unlocked gallery id=%d', $gallery->getId()));
        }
    }
}
