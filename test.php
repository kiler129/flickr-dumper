<?php
declare(strict_types=1);

use App\Flickr\Url\UrlParser;
use App\Struct\PhotoExtraFields;
use Symfony\Component\Finder\Finder;

require 'vendor/autoload.php';

$links = [
    //Photostream
    'https://www.flickr.com/photos/flickr'                                                                  => "A1: user's photostream (all photos)",
    'https://www.flickr.com/photos/66956608@N06'                                                            => "A2: user's w/NSID photostream (all photos)",

    //Individual photos
    'https://www.flickr.com/photos/flickr/52834970958'                                                      => "B1: regular photo of a user w/screenname",
    'https://www.flickr.com/photos/66956608@N06/52834970958'                                                => "B2: regular photo of a user w/NSID",

    //Individual photos in collections
    //  => in albums (contain user own photos only)
    'https://www.flickr.com/photos/flickr/15117816946/in/album-72157639858715274'                           => 'C1.1: user flickr photo 1511... in own album 7215...',
    'https://www.flickr.com/photos/66956608@N06/15117816946/in/album-72157639858715274'                     => 'C1.2: user w/NSID photo 1511... in own album 7215...',

    //  => in favorites (contain only photos of other users)
    'https://www.flickr.com/photos/flickr/52834970958/in/faves-198307123@N02/'                              => "C2.1: user flickr photo id 528... faved by user 198...",
    'https://www.flickr.com/photos/66956608@N06/52834970958/in/faves-198307123@N02/'                        => "C2.2: user w/NSID photo id 528... faved by user 198...",

    // => in galleries (contain only photos of other users)
    'https://www.flickr.com/photos/flickr/52834970958/in/gallery-198177581@N04-72157721691022506/'          => "C3.1: user flickr photo id 528... in user w/NSID 198... gallery 721..",
    'https://www.flickr.com/photos/66956608@N06/52834970958/in/gallery-198177581@N04-72157721691022506/'    => "C3.2: user w/NSID photo id 528... in user w/NSID 198... gallery 721..",
    'https://www.flickr.com/photos/garethwong/52868695051/in/gallery-flickr-72157721806781970/'             => "C3.3: user ger... photo id 528... in user flickr gallery 721...",
    'https://www.flickr.com/photos/garethwong/52868695051/in/gallery-66956608@N06-72157721806781970/'       => "C3.4: user ger... photo id 528... in user w/NSID gallery 721...",

    // => in pools/public groups
    'https://www.flickr.com/photos/cabodevassoura/34939199356/in/pool-flickrmeetup'                         => 'C4.1: user cab... photo id 349... in pool flickrmeetup',
    'https://www.flickr.com/photos/41032277@N07/34939199356/in/pool-flickrmeetup'                           => 'C4.2: user w/NSID photo id 349... in pool flickrmeetup',
    'https://www.flickr.com/photos/kedleson/52307221457/in/pool-52240293230@N01'                            => "C4.3: user ked... photo 523... in pool 522...",
    'https://www.flickr.com/photos/52995682@N07/52307221457/in/pool-52240293230@N01'                        => "C4.4: user w/NSID photo 523... in pool 522...",


    //Collections: albums (contain user own photos only)
    'https://www.flickr.com/photos/flickr/albums'                                                           => "D1: user's albums",
    'https://www.flickr.com/photos/66956608@N06/albums'                                                     => "D2: user's w/NSID albums",
    'https://www.flickr.com/photos/flickr/albums/72157639858715274'                                         => 'D3: album of a user w/screenname',
    'https://www.flickr.com/photos/66956608@N06/albums/72157639858715274'                                   => 'D4: album of a user w/NSID',
    'https://www.flickr.com/photos/flickr/albums/72157639858715274/page2'                                   => 'D5: album of a user, page 2',
    'https://www.flickr.com/photos/66956608@N06/albums/72157639858715274/page2'                             => 'D6: album of a user w/NSID, page 2',

    //Collections: favorites (contain only photos of other users)
    'https://www.flickr.com/photos/flickr/favorites'                                                        => "E1: favorites of a user w/screenname",
    'https://www.flickr.com/photos/66956608@N06/favorites'                                                  => "E2: favorites of a user w/NSID",
    'https://www.flickr.com/photos/flickr/favorites/page2'                                                  => "E3: favorites of a user w/screenname, page 2",
    'https://www.flickr.com/photos/66956608@N06/favorites/page2'                                            => "E4: favorites of a user w/NSID, page 2",

    //Collections: galleries (contain only photos of other users)
    'https://www.flickr.com/photos/flickr/galleries'                                                        => "F1: galleries of a user w/screenname",
    'https://www.flickr.com/photos/66956608@N06/galleries'                                                  => "F2: galleries of a user w/NSID",
    'https://www.flickr.com/photos/flickr/galleries/page2'                                                  => "F3: galleries of a user w/screenname",
    'https://www.flickr.com/photos/66956608@N06/galleries/page2'                                            => "F4: galleries of a user w/NSID",
    'https://www.flickr.com/photos/flickr/galleries/72157721806781970'                                      => "F5: gallery of a user w/screenname",
    'https://www.flickr.com/photos/66956608@N06/galleries/72157721806781970'                                => "F6: gallery of a user w/NSID",
        //technically speaking galleries have pages in the API but not in URLs


    //Collections: pools/public groups
    'https://www.flickr.com/groups/flickrmeetup/pool'                                                       => 'G1: pool flickrmeetup',
    'https://www.flickr.com/groups/flickrmeetup/pool/page2'                                                 => 'G2: pool flickrmeetup',
    'https://www.flickr.com/groups/flickrmeetup'                                                            => 'G3: pool flickrmeetup (implied pool)',


    'https://www.flickr.com/search/?sort=interestingness-desc&safe_search=1&tags=owl&min_taken_date=1682035200&max_taken_date=1684713599&view_all=1' => 'H1: search for owls',
];

$linksWithSlashes = [];
foreach ($links as $link => $name) {
    $linksWithSlashes[$link] = $name . ' (w/o trailing slash)';
    $linksWithSlashes[$link . '/'] = $name . ' (w/trailing slash)';
}

echo(substr(UrlParser::URL_REGEX, 1, -3)) . "\n\n";//die;
//echo implode("\n", \array_keys($linksWithSlashes)) . "\n";
//die;

//testOneByCode($links, 'C2');
//testOneByCode($links, 'C4');
//testOneByCode($links, 'D');
testAll($links);
//testAll($linksWithSlashes);





function getParsingResults(string $name, string $url, string $regex): string
{
    $result = "  As $name:\n";

    if (\preg_match($regex, $url, $results, \PREG_UNMATCHED_AS_NULL) !== 1) {
        return '❌' . $result . "\tNone\n";
    }

    $result = '✅' . $result;

    foreach ($results as $k => $v) {
        if (\is_int($k)) {
            continue;
        }

        $v ??= '<null>';
        $result .= "\t$k: $v\n";
    }

    return $result;
}

function testOne(string $link, string $name): void
{
    echo $link . "\n";
    echo $name . "\n";
    echo \str_repeat('-', 100);
    echo "\n";

    //echo getParsingResults('collection', $link, UrlParser::COLLECTION_VIEW_URL_REGEX) . "\n";
    //echo getParsingResults('item', $link, UrlParser::PHOTO_VIEW_URL_REGEX) . "\n";
    echo getParsingResults('combined', $link, UrlParser::URL_REGEX);
    //echo getParsingResults('ChatGPT', $link, GPT_REGEX);
    echo \str_repeat('*', 100);
    echo "\n";
    echo "\n";
}

function testAll(array $links): void
{
    foreach ($links as $link => $name) {
        testOne($link, $name);
    }
}

function testOneByCode(array $links, string $code)
{
    foreach ($links as $link => $name) {
        if (\str_starts_with($name, $code)) {
            testOne($link, $name);
        }
    }
}



die;


echo "\n";
