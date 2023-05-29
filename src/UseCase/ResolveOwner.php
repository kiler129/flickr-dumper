<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Entity\Flickr\PhotoCollection;
use App\Entity\Flickr\User;
use App\Exception\DomainException;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\Identity\UserIdentity;
use App\Flickr\Struct\PhotoDto;
use App\Flickr\Url\UrlGenerator;
use App\Repository\Flickr\UserRepository;
use Psr\Log\LoggerInterface;

class ResolveOwner
{
    public function __construct(
        private LoggerInterface $log,
        private UserRepository $userRepo,
        private UrlGenerator $urlGenerator,
        private FlickrApiClient $apiClient,
    ) {
    }

    /**
     * Attempts to find the owner object for a photo that potentially resides in a collection
     *
     * @param PhotoDto             $photoDto Photo straight from the API (no matter from where)
     * @param PhotoCollection|null $foundIn  If photo was found through a collection you can pass it to DRASTICALLY speed
     *                                      up the process in most cases
     *
     * @return User
     */
    public function resolveOwnerUser(PhotoDto $photoDto, ?PhotoCollection $foundIn): User
    {
        //If we can ensure that collection owner ALWAYS own all the photos (e.g. in albums) then we can simply take it
        if ($foundIn !== null && $foundIn::ownerOwnsPhotos()) {
            $owner = $foundIn->getOwner();
            $this->log->debug(
                'Owner id={oid} of photo id={phid} taken from collection {colType}',
                ['oid' => $owner->getNsid(), 'phid' => $photoDto->id, 'colType' => $foundIn::class]
            );

            return $owner;
        }

        //if (isset($photoDto->ownerNsid)) {
        //    $user = $this->userRepo->find($photoDto->ownerNsid);
        //    if ($user !== null) {
        //        return $user;
        //    }
        //}

        //$identity = $this->identifyPhotoUser($photoDto);
        //$user = new User($identity->nsid, $identity->userName, $identity->screenName);
        //$this->userRepo->save($user, true);

        return $this->identifyPhotoUser($photoDto);
    }

    private function identifyPhotoUser(PhotoDto $photoDto): User
    {
        $photoHasNsid = isset($photoDto->ownerNsid); //e.g. "1234@N05"
        $photoHasScreenName = isset($photoDto->ownerScreenName); //e.g. "spacex" (but CAN be null which is valid)
        $photoHasUserName = isset($photoDto->ownerUsername); //e.g. "Space X Photos"

        //Simplest case -> maybe the user just exists in the database
        if ($photoHasNsid) {
            $user = $this->userRepo->find($photoDto->ownerNsid);
                if ($user !== null) {
                    return $user;
                }
        }

        //In simple cases where the owner NSID exists in the API response (e.g. for faves) we can take a shortcut
        //However, this only avoids user lookup if we ALSO have owner screen name and username which is rare
        if ($photoHasNsid && $photoHasScreenName && $photoHasUserName) {
            $user = new User($photoDto->ownerNsid, $photoDto->ownerUsername, $photoDto->ownerScreenName);
            $this->userRepo->save($user, true);

            return $user;
        }

        if ($photoHasScreenName) {
            return $this->lookupUserByPathAlias($photoDto->ownerScreenName);
        }

        if ($photoHasUserName) {
            throw new \Exception('Not Implemented Scenario');
            //TODO: https://www.flickr.com/services/api/explore/flickr.people.findByUsername
        }

        throw new DomainException(
            \sprintf('Photo id=%s does not contain owner NSID, nor screen name, nor user name', $photoDto->id)
        );
    }

    /**
     * Attempts to translate screenname or NSID into verified identity containing NSID. It will NOT attempt username
     * lookup.
     *
     * Terminology help:
     *  - NSID: unique id of user, e.g. 130608600@N05
     *  - screenname: unique and optional "nick" of the user, used in URLs (e.g. "spacex")
     *  - username: unique display name of user (e.g. "Official SpaceX Photos")
     *
     * Keep in mind that both screenname and username CAN look exactly like the ID... even ID of other user, so there's
     * no static way to resolve these. The reason why this isn't trying username lookup is screenname and username are
     * not cross-unique. I.e. you can have user with <screenname=nasahqphoto username=NASA HQ PHOTO> and another user
     * with <username=nasahqphoto>.... ask me how I know ;D
     *
     * @deprecated I think... because in general lookupUserByPathAlias should be used?
     */
    public function lookupUserIdentityByPathAlias(string $screenNameOrNSID): ?UserIdentity
    {
        $user = $this->userRepo->findOneByIdentifier($screenNameOrNSID);
        if ($user !== null) {
            $this->log->debug(
                'Found user {user} in db as NSID={nsid}',
                ['user' => $screenNameOrNSID, 'nsid' => $user->getNsid()]
            );
            return new UserIdentity($user->getNsid(), $user->getUserName(), $user->getScreenName());
        }

        $profileUrl = $this->urlGenerator->getProfileLink($screenNameOrNSID);
        $lookup = $this->apiClient->getUrls()->lookupUser($profileUrl);
        if (!$lookup->isSuccessful()) {
            return null;
        }

        $data = $lookup->getContent();
        $screenName = $screenNameOrNSID !== $data['id'] ? $screenNameOrNSID : null;
        $this->log->debug(
            'Found user {user} via API as NSID={nsid}',
            ['user' => $screenNameOrNSID, 'nsid' => $data['id']]
        );

        return new UserIdentity($data['id'], $data['username']['_content'], $screenName);
    }

    /**
     * Same as lookupUserIdentityByPathAlias() but ensures entity lookup
     */
    public function lookupUserByPathAlias(string $screenNameOrNSID): ?User
    {
        $user = $this->userRepo->findOneByIdentifier($screenNameOrNSID);
        if ($user !== null) {
            $this->log->debug(
                'Found user {user} in db as NSID={nsid}',
                ['user' => $screenNameOrNSID, 'nsid' => $user->getNsid()]
            );
            return $user;
        }

        $profileUrl = $this->urlGenerator->getProfileLink($screenNameOrNSID);
        $lookup = $this->apiClient->getUrls()->lookupUser($profileUrl);
        if (!$lookup->isSuccessful()) {
            return null;
        }

        $data = $lookup->getContent();
        $screenName = $screenNameOrNSID !== $data['id'] ? $screenNameOrNSID : null;
        $this->log->debug(
            'Found user {user} via API as NSID={nsid} - saving to DB',
            ['user' => $screenNameOrNSID, 'nsid' => $data['id']]
        );

        //The user may still exist in the db but we don't have their screen name! Looking up by profile doesn't give
        //us the screen name unfortunately so it still may be missing
        $user = $this->userRepo->find($data['id']);
        if ($user !== null) {
            $this->log->debug(
                'Found user {user} is already in the DB - updating details',
                ['user' => $screenNameOrNSID, 'nsid' => $data['id']]
            );

            $user->setUserName($data['username']['_content'])
                 ->setScreenName($screenName);
        } else {
            $user = new User($data['id'], $data['username']['_content'], $screenName);
        }

        $this->userRepo->save($user, true);

        return $user;
    }
}
