<?php
declare(strict_types=1);

namespace App\Command\App;

use App\Command\IdentitySwitching;
use App\UseCase\ImportFiles\ImportLocalFiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:import-folder',
    description: 'Attempts to fuzzy-import existing Flickr dumps into cohesive database',
)]
class ImportFolderCommand extends Command
{
    use IdentitySwitching;

    private SymfonyStyle $io;

    /**
     * @param callable(): ImportLocalFiles $importUC
     */
    public function __construct(private \Closure $importUC)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
                'extensions',
                null,
                InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,
                'Extensions of photos to consider',
                ['jpg', 'jpeg', 'tiff', 'png']
            )
            ->addOption('min-size', null, InputOption::VALUE_REQUIRED, 'Minimum file size to be considered an importable image', '500K')
            ->addOption('recover-meta', null, InputOption::VALUE_NONE, 'Attempt to recover metadata from API dumps (advanced)')
            ->addOption('copy-files', null, InputOption::VALUE_NEGATABLE, 'Enable/disable copying of files to root storage', true)
            ->addArgument('dir', InputArgument::REQUIRED, 'Directory to import')
        ;

        $this->addSwitchIdentitiesOption($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resolveSwitchIdentities($input);
        $this->io = new SymfonyStyle($input, $output);

        $dir = $input->getArgument('dir');
        if (!\is_dir($dir)) {
            $this->io->error(\sprintf('Path "%s" is not a valid directory', $dir));
            return Command::FAILURE;
        }

        $importUC = ($this->importUC)(); //get new instance
        $importUC->attemptMetadataRecovery = $input->getOption('recover-meta');
        $importUC->copyFilesToStorage = $input->getOption('copy-files');
        $importUC->switchIdentities = $this->switchIdentities;

        $this->processDirectory(
            $importUC,
            $output,
            $dir,
            $input->getOption('extensions'),
            $input->getOption('min-size'),
        );

        return Command::SUCCESS;
    }

    private function processDirectory(
        ImportLocalFiles $importUC,
        OutputInterface $output,
        string $dir,
        array $allowedExtensions,
        ?string $minSize
    ) {
        $finder = new Finder();
        $finder
            ->files()
            ->in($dir)
            ->ignoreUnreadableDirs()
            ->name(\sprintf('/\.(%s)$/i', \implode('|', $allowedExtensions)))
        ;

        if ($minSize !== null) {
            $finder->size('>= ' . $minSize);
        }

        $bar = new ProgressBar($output);
        $bar->setEmptyBarCharacter('▱');
        $bar->setProgressCharacter('');
        $bar->setBarCharacter('▰');
        $bar->minSecondsBetweenRedraws(0.25);
        $bar->start();


        foreach ($finder as $file) {
            $importUC->importFile($file);
            $bar->advance();
        }

        $bar->finish();
        $bar->clear();
    }
}
