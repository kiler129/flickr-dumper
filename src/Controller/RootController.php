<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use App\UseCase\View\GenerateBreadcrumbs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RootController extends AbstractController
{
    public function __construct(private UserRepository $userRepo, private PhotosetRepository $photosetRepo, private GenerateBreadcrumbs $breadcrumbsUC)
    {
    }

    #[Route('/', methods: ['GET'], name: 'app.index')]
    public function index(): Response
    {
        return $this->render(
            'index.html.twig',
            ['extra' => ['breadcrumbs' => $this->breadcrumbsUC->forHome('Flickr Sync Browser')]]
        );
    }
}
