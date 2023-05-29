<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\Photoset;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use App\Struct\View\BreadcrumbDto;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RootController extends AbstractController
{
    public function __construct(private UserRepository $userRepo, private PhotosetRepository $photosetRepo)
    {
    }

    #[Route('/', methods: ['GET'], name: 'app.index')]
    public function index(): Response
    {
        $breadcrumbs = [
            new BreadcrumbDto('Flickr Sync Browser'),
        ];

        return $this->render('index.html.twig', ['extra' => ['breadcrumbs' => $breadcrumbs]]);
    }

    #[Route('/albums', methods: ['GET'], name: 'app.albums_all')]
    public function allAlbums(): Response
    {
        $breadcrumbs = [
            new BreadcrumbDto('All Albums'),
        ];

        return $this->render('album/list.html.twig',
                             [
                                 'list' => $this->photosetRepo->findAllDisplayable(),
                                 'extra' => ['breadcrumbs' => $breadcrumbs],
                             ]
        );
    }

    #[Route('/albums/{albumId}', methods: ['GET'], name: 'app.albums_redirect_to_user_album')]
    public function album(#[MapEntity(mapping: ['albumId' => 'id'])] Photoset $album): Response
    {
        return $this->redirectToRoute('app.photos_in_album',
                               [
                                   'albumId' => $album->getId(),
                                   'userId' => $album->getOwner()->getNsid(),
                               ]
        );
    }
}
