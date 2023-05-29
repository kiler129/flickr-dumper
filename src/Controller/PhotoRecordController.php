<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flickr\Photo;
use App\Exception\LogicException;
use App\Repository\Flickr\PhotoRepository;
use Spatie\Image\Image;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
            $photo->localRanking->triggerView();
            $this->photoRepo->save($photo, true);
        }

        return new BinaryFileResponse($photo->getLocalPath(), public: false, autoEtag: true, autoLastModified: true);
    }

    #[Route('/photo/thumbnail/{photoId}', methods: ['GET'], name: 'app.photo_thumb_bin')]
    public function thumbnail(#[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        $localPath = $photo->getLocalPath();
        $thumbPath  = $localPath . '.thumb';

        if (!\file_exists($thumbPath)) {
            Image::load($localPath)
                 ->width(1024)
                 ->height(1024)
                 ->quality(70)
                 ->save($thumbPath);
        }

        return new BinaryFileResponse($thumbPath, public: false, autoEtag: true, autoLastModified: true);
    }

    #[Route('/photo/vote/{photoId}/up', methods: ['POST'], name: 'app.photo_vote_up')]
    public function voteUp(#[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        return $this->vote($photo, true);
    }

    #[Route('/photo/vote/{photoId}/down', methods: ['POST'], name: 'app.photo_vote_down')]
    public function voteDown(#[MapEntity(mapping: ['photoId' => 'id'])] Photo $photo): Response
    {
        return $this->vote($photo, false);
    }

    private function vote(Photo $photo, bool $isGood): Response
    {
        if ($isGood) {
            $photo->localRanking->upVote();
        } else {
            $photo->localRanking->downVote();
        }

        $this->photoRepo->save($photo, true);
        $votesNow = $photo->localRanking->voteRanking();

        return new Response((string)$votesNow, headers: ['Content-Type' => 'text/plain']);
    }
}
