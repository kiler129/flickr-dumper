<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\Photoset;
use App\Entity\Flickr\User;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use App\UseCase\View\GenerateBreadcrumbs;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PhotosetController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 20;

    public function __construct(
        private PhotosetRepository $photosetRepo,
        private GenerateBreadcrumbs $breadcrumbsUC
    ) {
    }

    #[Route('/albums', methods: ['GET'], name: 'app.albums_all')]
    public function showAllPhotosets(Request $request): Response
    {
        $pagerfanta = new Pagerfanta(new QueryAdapter($this->photosetRepo->createForAllDisplayable()));
        $pagerfanta->setMaxPerPage(self::DEFAULT_PER_PAGE)
                   ->setCurrentPage($request->query->getInt('page', 1));

        return $this->render(
            'album/list.html.twig',
            [
             'pager' => $pagerfanta,
             'extra' => ['breadcrumbs' => $this->breadcrumbsUC->forAllAlbums()],
            ]
        );
    }

    #[Route('/albums/{albumId}', methods: ['GET'], name: 'app.albums_redirect_to_user_album')]
    public function redirectToUserPhotoset(#[MapEntity(mapping: ['albumId' => 'id'])] Photoset $album): Response
    {
        return $this->redirectToRoute(
            'app.photos_in_album',
            [
                'albumId' => $album->getId(),
                'userId' => $album->getOwner()->getNsid(),
            ],
            Response::HTTP_PERMANENTLY_REDIRECT
        );
    }

    #[Route('/user/{userId}/album', methods: ['GET'], name: 'app.user_resources_albums')]
    public function showUserPhotoset(Request $request, #[MapEntity(mapping: ['userId' => 'nsid'])] User $user): Response
    {
        $pagerfanta = new Pagerfanta(
            new QueryAdapter($this->photosetRepo->createForAllDisplayableForUser($user->getNsid()))
        );
        $pagerfanta->setMaxPerPage(self::DEFAULT_PER_PAGE)
                   ->setCurrentPage($request->query->getInt('page', 1));

        return $this->render(
            'album/list.html.twig',
            ['pager' => $pagerfanta, 'extra' => ['breadcrumbs' => $this->breadcrumbsUC->forUserAlbumsList($user)]]
        );
    }
}
