<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\Photo;
use App\Repository\Flickr\PhotoRepository;
use App\UseCase\GetThumbnail;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PhotoRecordController extends AbstractController
{
    public function __construct(private PhotoRepository $photoRepo)
    {
    }

    #[Route('/photo/show/{photoId}', methods: ['GET'], name: 'app.photo_file_bin')]
    public function show(Request $request, #[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        if (!$request->query->has('notrack')) {
            $photo->localStats->triggerView();
            $this->photoRepo->save($photo, true);
        }

        return new BinaryFileResponse($photo->getLocalPath(), public: false, autoEtag: true, autoLastModified: true);
    }

    #[Route('/photo/thumbnail/{photoId}', methods: ['GET'], name: 'app.photo_thumb_bin')]
    public function thumbnail(
        Request $request,
        #[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo,
        GetThumbnail $thumbnailUC
    ): Response
    {
        return new BinaryFileResponse(
                              $thumbnailUC->getThumbnailForPhoto($photo, $request->query->has('refresh')),
            public:           false,
            autoEtag:         true,
            autoLastModified: true
        );
    }

    #[Route('/photo/modify/{photoId}/up', methods: ['POST'], name: 'app.photo_vote_up')]
    public function voteUp(#[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        return $this->vote($photo, true);
    }

    #[Route('/photo/modify/{photoId}/down', methods: ['POST'], name: 'app.photo_vote_down')]
    public function voteDown(#[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        return $this->vote($photo, false);
    }

    #[Route('/photo/modify/{photoId}/softDelete', methods: ['POST'], name: 'app.photo_soft_delete')]
    public function softDelete(#[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        if ($photo->isDeleted()) {
            throw new ConflictHttpException('Photo is already deleted');
        }

        $photo->setDeleted();
        $this->photoRepo->save($photo, true);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function vote(Photo $photo, bool $isGood): Response
    {
        if ($isGood) {
            $photo->localStats->upVote();
        } else {
            $photo->localStats->downVote();
        }

        $this->photoRepo->save($photo, true);
        $votesNow = $photo->localStats->voteRanking();

        return new Response((string)$votesNow, headers: ['Content-Type' => 'text/plain']);
    }
}
