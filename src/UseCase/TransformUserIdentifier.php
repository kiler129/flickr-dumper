<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\Identity\UserIdentity;
use App\Flickr\Url\UrlGenerator;
use App\Repository\Flickr\UserRepository;

class TransformUserIdentifier
{
    public function __construct(
        private UserRepository $userRepo,
        private UrlGenerator $urlGenerator,
        private FlickrApiClient $apiClient,
    ) {

    }

    /**
     * Attempts to translate screenname or NSID into verified identity containing NSID. It will NOT attempt username
     * lookup.
     *
     * Terminology help:
     *  - NSID: unique id of user, e.g. 130608600@N05
     *  - screenname: unique and optional "nick" of the user, used in URLs (e.g. "spacex")
     *  - username: non-unique display name of user (e.g. "Official SpaceX Photos")
     *
     * Keep in mind that both screenname and username CAN look exactly like the ID... even ID of other user, so there's
     * no static way to resolve these.
     */
    public function lookupUserByIdentifier(string $screenNameOrNSID): ?UserIdentity
    {
        $user = $this->userRepo->findOneByIdentifier($screenNameOrNSID);
        if ($user !== null) {
            return new UserIdentity($user->getNsid(), $user->getUserName(), $user->getScreenName());
        }

        $profileUrl = $this->urlGenerator->getProfileLink($screenNameOrNSID);
        $lookup = $this->apiClient->getUrls()->lookupUser($profileUrl);
        if (!$lookup->isSuccessful()) {
            return null;
        }

        $data = $lookup->getContent();
        $screenName = $screenNameOrNSID !== $data['id'] ? $screenNameOrNSID : null;

        return new UserIdentity($data['id'], $data['username']['_content'], $screenName);
    }
}
