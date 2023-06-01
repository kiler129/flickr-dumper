<?php
declare(strict_types=1);

namespace App\UseCase\View;

use App\Entity\Flickr\Collection\Gallery;
use App\Entity\Flickr\Collection\Photoset;
use App\Entity\Flickr\User;
use App\Struct\View\BreadcrumbDto;
use Symfony\Component\Routing\RouterInterface;

class GenerateBreadcrumbs
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function forHome(string $homeTitle = 'Home'): array
    {
        return [
            new BreadcrumbDto($homeTitle, $this->router->generate('app.index')),
        ];
    }

    public function forAllPhotos(): array
    {
        $out = $this->forHome();
        $out[] = new BreadcrumbDto('All Photos', $this->router->generate('app.photos_all'));

        return $out;
    }

    public function forAllAlbums(): array
    {
        $out = $this->forHome();
        $out[] = new BreadcrumbDto('All Albums', $this->router->generate('app.albums_all'));

        return $out;
    }

    public function forAllGalleries(): array
    {
        $out = $this->forHome();
        $out[] = new BreadcrumbDto('All Galleries', $this->router->generate('app.galleries_all'));

        return $out;
    }

    public function forAllUsers(): array
    {
        $out = $this->forHome();
        $out[] = new BreadcrumbDto('All Users', $this->router->generate('app.users_list'));

        return $out;
    }

    public function forUser(User $user): array
    {
        $out = $this->forAllUsers();
        $out[] = new BreadcrumbDto(
            $user->getDisplayableShortName(),
            $this->router->generate('app.user_resources', ['userId' => $user->getNsid()])
        );

        return $out;
    }

    public function forUserAlbumsList(User $user): array
    {
        $out = $this->forUser($user);
        $out[] = new BreadcrumbDto(
            'Albums',
            $this->router->generate('app.user_resources_albums', ['userId' => $user->getNsid()])
        );

        return $out;
    }

    public function forUserGalleriesList(User $user): array
    {
        $out = $this->forUser($user);
        $out[] = new BreadcrumbDto(
            'Galleries',
            $this->router->generate('app.user_resources_galleries', ['userId' => $user->getNsid()])
        );

        return $out;
    }

    public function forUserFavorites(User $user): array
    {
        $out = $this->forUser($user);
        $out[] = new BreadcrumbDto(
            'Favorite Photos',
            $this->router->generate('app.user_resources_favorites', ['userId' => $user->getNsid()])
        );

        return $out;
    }

    public function forUserPhotos(User $user): array
    {
        $out = $this->forUser($user);
        $out[] = new BreadcrumbDto(
            'User Own Photos',
            $this->router->generate('app.user_resources_photos', ['userId' => $user->getNsid()])
        );

        return $out;
    }

    public function forAlbum(User $user, Photoset $photoset): array
    {
        $out = $this->forUserAlbumsList($user);
        $out[] = new BreadcrumbDto(
            $photoset->getTitle() ?? \sprintf('Album #%d', $photoset->getId()),
            $this->router->generate(
                'app.photos_in_album',
                ['userId' => $user->getNsid(), 'albumId' => $photoset->getId()])
        );

        return $out;
    }

    public function forGallery(User $user, Gallery $gallery): array
    {
        $out = $this->forUserGalleriesList($user);
        $out[] = new BreadcrumbDto(
            $gallery->getTitle() ?? \sprintf('Gallery #%d', $gallery->getId()),
            $this->router->generate(
                'app.photos_in_gallery',
                ['userId' => $user->getNsid(), 'galleryId' => $gallery->getId()])
        );

        return $out;
    }
}
