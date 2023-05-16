<?php
declare(strict_types=1);

namespace App\Flickr;

/** @deprecated use FlickrApiCLient->getTest() */
class Test
{
    private BaseApiClient $baseClient;

    public function __construct(BaseApiClient $baseClient)
    {
        $this->baseClient = $baseClient;
    }

    /**
     * Calls flickr.test.echo
     *
     * Normally it should be named "echo" but it's a reserved keyword ;)
     *
     * @return array
     */
    public function echoTest(): array
    {
        return $this->baseClient->callMethod('flickr.test.echo');
    }

    public function nullTest(): array
    {
        return $this->baseClient->callMethod('flickr.test.null');
    }
}
