<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

trait OwnerAware
{
    /**
     * While $owner can contain anything Flickr URLs understand, $ownerNSID is for-sure an ID.
     * This DTO can be "filled-in" later on when it's discovered the true NSID. Until $ownerNSID is set you cannot know
     * what $owner really contains.
     */
    public readonly string $ownerNSID;

    /**
     * @var string Username or NSID. (!) DO NOT assume $owner is NSID - it may be, but it can be a screenname. You CAN'T
     *             blindly try using it as NSID, and you must resolve it via API or database. See Flickr\Url\UrlParser
     *             for detailed explanation why.
     */
    public readonly string $owner;

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function hasNSID(): bool
    {
        return isset($this->ownerNSID);
    }

    public function setNSID(string $ownerNSID): void
    {
        $this->ownerNSID = $ownerNSID;
    }
}
