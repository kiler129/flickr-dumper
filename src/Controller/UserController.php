<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\User;
use App\Repository\Flickr\UserRepository;
use App\Struct\View\BreadcrumbDto;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(private UserRepository $userRepo)
    {
    }

    #[Route('/user', methods: ['GET'], name: 'app.users_list')]
    public function allUsers(): Response
    {
        $breadcrumbs = [
            new BreadcrumbDto('Home', $this->generateUrl('app.index')),
            new BreadcrumbDto('All Users'),
        ];

        return $this->render('user/list.html.twig', ['list' => $this->userRepo->findAll(), 'extra' => ['breadcrumbs' => $breadcrumbs]]);
    }

    #[Route('/user/{userId}', methods: ['GET'], name: 'app.user_resources')]
    public function showAll(Request $request, #[MapEntity(mapping: ['userId' => 'nsid'])] User $user): Response
    {
        return $this->render('user/resources.html.twig', ['user' => $user]);
    }

    #[Route('/user/{userId}/album', methods: ['GET'], name: 'app.user_resources_albums')]
    public function showAlbums(Request $request, #[MapEntity(mapping: ['userId' => 'nsid'])] User $user): Response
    {
        //return $this->render('user/resources.html.twig', ['user' => $user]);
    }


}
