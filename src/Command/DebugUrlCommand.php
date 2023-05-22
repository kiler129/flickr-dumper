<?php
declare(strict_types=1);

namespace App\Command;

use App\Flickr\Url\UrlParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;

#[AsCommand(name: 'debug:flickr-url')]
final class DebugUrlCommand extends Command
{
    private const URLS = [
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


        'https://www.flickr.com/search/?sort=interestingness-desc&tags=owl'                                     => 'H1: search for owls',
    ];

    private SymfonyStyle $io;
    private VarCloner    $cloner;
    private CliDumper    $dumper;

    public function __construct(private UrlParser $urlParser) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption(
            'list',
            'l',
            InputOption::VALUE_OPTIONAL,
            'List known sample URLs, optionally filtering by the category',
            false
        )
            ->addOption(
                'test',
                't',
                InputOption::VALUE_OPTIONAL,
                'Parse URLs, optionally filtering by the category',
                false
            )
            ->addOption(
                'add-equivalents',
                 null,
                 InputOption::VALUE_NONE,
                 'Automatically generate & add equivalent URLs (e.g. with/without leading slash)'
             )
             ->addOption('regex', null, InputOption::VALUE_NONE, 'Include raw regex parsing result')
             ->addArgument(
                 'url',
                 InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                 'Optionally URL to test if not using --test-all'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $userLinks = $input->getArgument('url');
        $userLinksCount = \count($userLinks);
        $testUrls = $input->getOption('test'); //false if not passed; null for empty list; string for category name
        $listUrls = $input->getOption('list'); //false if not passed; null for empty list; string for category name

        if ($userLinksCount === 0 && $testUrls === false && $listUrls === false) {
            $this->io->warning('Nothing to do - you should specify at least the URL argument or --list or --test');

            return Command::FAILURE;
        }

        if ($userLinksCount !== 0) {
            $list = [];
            foreach ($userLinks as $idx => $link) {
                $list[$link] = 'user link #' . ($idx+1);
            }
        } else {
            $list = self::URLS;
        }

        if ($input->getOption('add-equivalents')) {
            $list = $this->deriveEquivalents($list);
        }

        if ($listUrls !== false) {
            $this->listUrls($this->filterList($list, $listUrls), $listUrls);
        }

        if ($testUrls !== false) {
            $this->testUrls($this->filterList($list, $testUrls), $testUrls, $input->getOption('regex'));
        }


        return Command::SUCCESS;
    }

    private function deriveEquivalents(array $links): array
    {
        $out = [];
        foreach ($links as $link => $desc) {
            if (\str_contains($link, '/?')) {
                $out[$link] = $desc;
                continue;
            }

            $link = \rtrim($link, '/');
            $out[$link] = $desc . ' (w/o trailing slash)';
            $out[$link . '/'] = $desc . ' (w/ trailing slash)';
        }

        return $out;
    }

    private function filterList(array $list, ?string $filter): array
    {
        if ($filter === null) {
            return $list;
        }

        $newList = [];
        foreach ($list as $link => $desc) {
            if (\str_starts_with($desc, $filter)) {
                $newList[$link] = $desc;
            }
        }

        return $newList;
    }

    private function listUrls(array $list, ?string $filter): void
    {
        $title = 'List of URLs';
        if ($filter !== null) {
            $title .= \sprintf(' (filtered by "%s")', $filter);
        }
        $this->io->title($title);

        $rows = [];
        $lastCat = null;
        foreach ($list as $link => $desc)
        {
            $thisCat = $desc[0];
            if ($lastCat === null) {
                $lastCat = $thisCat;
            } elseif ($lastCat !== $thisCat) {
                $lastCat = $thisCat;
                $rows[] = new TableSeparator();
            }

            $split = \explode(': ', $desc, 2);
            if (isset($split[1])) {
                $rows[] = [$split[0], \ucfirst($split[1]), $link];
            } else {
                $rows[] = ['N/A', \ucfirst($desc), $link];
            }
        }

        $this->io->table(['Category', 'Name', 'URL'], $rows);
    }

    private function testUrls(array $list, ?string $filter, bool $regex): void
    {
        $title = 'URLs parsing result';
        if ($filter !== null) {
            $title .= \sprintf(' (filtered by "%s")', $filter);
        }
        $this->io->title($title);

        $cols = ['Link'];
        if ($regex) {
            $cols[] = 'Regex';
        }
        $cols[] = 'Media Collection Id';
        $cols[] = 'Item Id';

        $rows = [];
        $lastCat = null;
        foreach ($list as $link => $desc) {
            $thisCat = $desc[0];
            if ($lastCat === null) {
                $lastCat = $thisCat;
            } elseif ($lastCat !== $thisCat) {
                $lastCat = $thisCat;
                $rows[] = new TableSeparator();
            }

            $collectionId = $this->urlParser->getMediaCollectionIdentity($link);
            $itemId = $this->urlParser->getMediaIdentity($link);

            $row = [
                $link . "\n" . $desc . "\n" .
                (\preg_match(UrlParser::URL_REGEX, $link) === 1 ? '✅ Valid' : '❌ Invalid'),
            ];

            if ($regex) {
                $row[] = $this->parseRegex($link);
            }

            $row[] = $collectionId === null ? '❌' : $this->dumpVariable($collectionId);
            $row[] = $itemId === null ? '❌' : $this->dumpVariable($itemId);
            $rows[] = $row;
        }

        $this->io->table($cols, $rows);
    }

    private function parseRegex(string $link): string
    {
        if (\preg_match(UrlParser::URL_REGEX, $link, $results, \PREG_UNMATCHED_AS_NULL) !== 1) {
            return '<did not match>';
        }

        $result = [];
        foreach ($results as $k => $v) {
            if (\is_int($k)) {
                continue;
            }

            $result[$k] = $v;
        }

        return $this->dumpVariable($result);
    }

    private function dumpVariable(mixed $var): string
    {
        $this->cloner ??= new VarCloner();
        $this->dumper ??= new CliDumper(null, null, AbstractDumper::DUMP_LIGHT_ARRAY);
        $this->dumper->setColors(true);

        return $this->dumper->dump($this->cloner->cloneVar($var), true) ?? '<null>';
    }
}
