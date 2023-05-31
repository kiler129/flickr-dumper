<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\Photoset;
use App\Entity\Flickr\User;
use App\Exception\LogicException;
use App\Repository\Flickr\PhotoRepository;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserFavoritesRepository;
use App\Struct\View\BreadcrumbDto;
use App\Struct\View\PhotoPredefinedFilter;
use App\Struct\View\PhotoSuggestedSort;
use App\UseCase\View\GenerateBreadcrumbs;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @phpstan-type TGetQb callable(array $filters, string $orderBy, string $orderDir): QueryBuilder
 */
class PhotoListController extends AbstractController
{
    public function __construct(private PhotoRepository $photoRepo, private PhotosetRepository $photosetRepo, private GenerateBreadcrumbs $breadcrumbsUC)
    {
    }

    #[Route('/photo/all', methods: ['GET'], name: 'app.photos_all')]
    public function showAll(Request $request): Response
    {
        return $this->displayGenericCollection(
            $request,
            fn(array $filters, string $orderBy, string $orderDir) => $this->photoRepo->createArbitraryFiltered(
                $filters,
                $orderBy,
                $orderDir
            ),
            ['breadcrumbs' => $this->breadcrumbsUC->forAllPhotos()]
        );
    }

    #[Route('/user/{userId}/album/{albumId}', methods: ['GET'], name: 'app.photos_in_album')]
    public function showAlbum(
        Request $request,
        #[MapEntity(mapping: ['userId' => 'nsid'])] User $user,
        #[MapEntity(mapping: ['albumId' => 'id'])] Photoset $album): Response
    {
        if ($album->getOwner() !== $user) {
            throw new BadRequestException(
                \sprintf(
                    'User "%s" is not an owner of album "%d" (owned by "%s")',
                    $user->getNsid(),
                    $album->getId(),
                    $album->getOwner()
                          ->getNsid()
                )
            );
        }

        return $this->displayGenericCollection(
            $request,
            fn(array $filters, string $orderBy, string $orderDir) => $this->photosetRepo->createForAllPhotosInAlbum(
                $album->getId(),
                $filters,
                $orderBy,
                $orderDir
            ),
            ['breadcrumbs' => $this->breadcrumbsUC->forAlbum($user, $album)]
        );
    }

    #[Route('/user/{userId}/photos', methods: ['GET'], name: 'app.user_resources_photos')]
    public function showUserPhotos(Request $request, #[MapEntity(mapping: ['userId' => 'nsid'])] User $user,)
    {
        return $this->displayGenericCollection(
            $request,
            fn(array $filters, string $orderBy, string $orderDir) => $this->photoRepo->createArbitraryFiltered(
                ['owner' => $user->getNsid()] + $filters,
                $orderBy,
                $orderDir
            ),
            ['breadcrumbs' => $this->breadcrumbsUC->forUserPhotos($user)]
        );
    }

    #[Route('/user/{userId}/faves', methods: ['GET'], name: 'app.user_resources_favorites')]
    public function showUserFavoritePhotos(
        Request $request,
        #[MapEntity(mapping: ['userId' => 'nsid'])] User $user,
        UserFavoritesRepository $userFavesRepo,
    ) {
        return $this->displayGenericCollection(
            $request,
            fn(array $filters, string $orderBy, string $orderDir) => $userFavesRepo->createForAllPhotosInFavorites(
                $user->getNsid(),
                $filters,
                $orderBy,
                $orderDir
            ),
            ['breadcrumbs' => $this->breadcrumbsUC->forUserFavorites($user)]
        );
    }

    /**
     * @param TGetQb $queryBuilder
     */
    private function displayGenericCollection(Request $request, callable $queryBuilder, array $extraParams = []): Response
    {
        $filters = $request->query->all()['filters'] ?? [];
        $orderBy = $request->query->get('orderBy', 'dateTaken');
        $orderDir = \strtoupper($request->query->get('orderDir', 'DESC'));
        $page = $request->query->getInt('page', 1);

        $filters['status.deleted'] ??= false;
        //$filters['status.blacklisted'] ??= false;
        $filters['status.writeLockedAt'] ??= null;

        $pagerfanta = new Pagerfanta(new QueryAdapter($queryBuilder($filters, $orderBy, $orderDir)));
        $pagerfanta->setMaxPerPage(50)
                   ->setCurrentPage($page);

        return $this->render('photo/list.html.twig',
                             [
                                 'pager' => $pagerfanta,
                                 'suggestedSort' => PhotoSuggestedSort::getAll(),
                                 'predefinedFilters' => PhotoPredefinedFilter::getAll(),
                                 'currentSort' => ['field' => $orderBy, 'dir' => $orderDir],
                                 'currentFilters' => $filters,
                                 'extra' => $extraParams,
                             ]
        );
    }
}
