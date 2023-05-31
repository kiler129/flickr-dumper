<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\User;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use App\Struct\View\BreadcrumbDto;
use App\UseCase\View\GenerateBreadcrumbs;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepo,
        private GenerateBreadcrumbs $breadcrumbsUC
    ) {
    }

    #[Route('/user', methods: ['GET'], name: 'app.users_list')]
    public function allUsers(Request $request): Response
    {
        $pagerfanta = new Pagerfanta(new QueryAdapter($this->userRepo->createAllWithProperties()));
        $pagerfanta->setMaxPerPage(20)
                   ->setCurrentPage($request->query->getInt('page', 1));

        return $this->render(
            'user/list.html.twig',
            [
                'pager' => $pagerfanta,
                'extra' => ['breadcrumbs' => $this->breadcrumbsUC->forAllUsers()],
            ]
        );
    }

    #[Route('/user/{userId}', methods: ['GET'], name: 'app.user_resources')]
    public function showUser(Request $request, #[MapEntity(mapping: ['userId' => 'nsid'])] User $user): Response
    {
        return $this->render(
            'user/resources.html.twig',
            ['user' => $user, 'extra' => ['breadcrumbs' => $this->breadcrumbsUC->forUser($user)]]
        );
    }
}
