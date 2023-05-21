<?php
declare(strict_types=1);

use App\Struct\PhotoExtraFields;
use Symfony\Component\Finder\Finder;

require 'vendor/autoload.php';

class foo {
    readonly public string $whatLuklewLikes;


    public function __construct(private string $whatLuklewReallyLikes)
    {
        $this->whatLuklewLikes = &$this->whatLuklewReallyLikes;
    }

    public function magic(string $wizard): void
    {

    }
}


$foo = new foo('Python');
dump($foo->whatLuklewLikes);



die;



foreach ([
             PhotoExtraFields::DESCRIPTION,
             PhotoExtraFields::DATE_UPLOAD,
             PhotoExtraFields::DATE_TAKEN,
             PhotoExtraFields::LAST_UPDATE,
             PhotoExtraFields::VIEWS,
             PhotoExtraFields::MEDIA,
             PhotoExtraFields::PATH_ALIAS,

             //We're only requesting sensibly-sized pictures, not thumbnails
             PhotoExtraFields::URL_MEDIUM_640,
             PhotoExtraFields::URL_MEDIUM_800,
             PhotoExtraFields::URL_LARGE_1024,
             PhotoExtraFields::URL_LARGE_1600,
             PhotoExtraFields::URL_LARGE_2048,
             PhotoExtraFields::URL_XLARGE_3K,
             PhotoExtraFields::URL_XLARGE_4K,
             PhotoExtraFields::URL_XLARGE_4K_2to1,
             PhotoExtraFields::URL_XLARGE_5K,
             PhotoExtraFields::URL_XLARGE_6K,
             PhotoExtraFields::URL_ORIGINAL,
         ] as $ef) {
    echo $ef->value . ',';
}

echo "\n";
