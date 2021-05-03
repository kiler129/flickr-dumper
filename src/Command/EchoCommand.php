<?php

namespace App\Command;

use App\Exception\Api\ApiCallException;
use App\Flickr\PhotoSets;
use App\Flickr\Test;
use App\Flickr\Urls;
use App\Struct\PhotoExtraFields;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EchoCommand extends Command
{
    protected static  $defaultName = 'flickr:echo';
    private Test      $test;
    private Urls      $flickrUrls;
    private PhotoSets $flickrAlbums;

    public function __construct(Test $test, Urls $flickrUrls, PhotoSets $flickrAlbums)
    {
        $this->test = $test;
        $this->flickrUrls = $flickrUrls;
        $this->flickrAlbums = $flickrAlbums;

        parent::__construct();
    }


    protected function configure()
    {
        //$this
            //->setDescription(self::$defaultDescription)
        //;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //$nsid = $this->flickrUrls->lookupUserId('https://www.flickr.com/photos/nasahqphoto');
        $nsid = '97346958@N03';

        try {
            //dd($this->flickrAlbums->getList($nsid, 1, 5));
            //dd($this->flickrAlbums->getPhotos($nsid, '72157719045178117'));
            //dd($this->flickrAlbums->getPhotosAllFlat($nsid, '72157719032393683', PhotoSets::MAX_PER_PAGE, [PhotoExtraFields::URL_ORIGINAL]));
            dd($this->flickrAlbums->getPhotosAllFlat($nsid, '72157719045178117', PhotoSets::MAX_PER_PAGE, [PhotoExtraFields::URL_ORIGINAL]));
        } catch (ApiCallException $e) {
            dd($e);
        }

        return Command::SUCCESS;
    }
}
