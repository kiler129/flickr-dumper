<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\Collection\Gallery;
use App\Entity\Flickr\User;
use App\Repository\Flickr\GalleryRepository;
use App\UseCase\View\GenerateBreadcrumbs;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class GalleryController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 20;

    public function __construct(
        private GalleryRepository $galleryRepo,
        private GenerateBreadcrumbs $breadcrumbsUC
    ) {
    }

    #[Route('/galleries', methods: ['GET'], name: 'app.galleries_all')]
    public function showAllGalleries(Request $request): Response
    {
        $pagerfanta = new Pagerfanta(new QueryAdapter($this->galleryRepo->createForAllDisplayable()));
        $pagerfanta->setMaxPerPage(self::DEFAULT_PER_PAGE)
                   ->setCurrentPage($request->query->getInt('page', 1));

        return $this->render(
            'gallery/list.html.twig',
            [
             'pager' => $pagerfanta,
             'extra' => ['breadcrumbs' => $this->breadcrumbsUC->forAllGalleries()],
            ]
        );
    }

    #[Route('/galleries/{galleryId}', methods: ['GET'], name: 'app.galleries_redirect_to_user_gallery')]
    public function redirectToUserGallery(#[MapEntity(mapping: ['galleryId' => 'id'])] Gallery $gallery): Response
    {
        return $this->redirectToRoute(
            'app.photos_in_gallery',
            [
                'galleryId' => $gallery->getId(),
                'userId' => $gallery->getOwner()->getNsid(),
            ],
            Response::HTTP_PERMANENTLY_REDIRECT
        );
    }

    #[Route('/user/{userId}/gallery', methods: ['GET'], name: 'app.user_resources_galleries')]
    public function showUserGalleries(
        Request $request,
        #[MapEntity(mapping: ['userId' => 'nsid'])] User $user
    ): Response {
        $pagerfanta = new Pagerfanta(
            new QueryAdapter($this->galleryRepo->createForAllDisplayableForUser($user->getNsid()))
        );
        $pagerfanta->setMaxPerPage(self::DEFAULT_PER_PAGE)
                   ->setCurrentPage($request->query->getInt('page', 1));

        return $this->render(
            'gallery/list.html.twig',
            ['pager' => $pagerfanta, 'extra' => ['breadcrumbs' => $this->breadcrumbsUC->forUserGalleriesList($user)]]
        );
    }
}
