<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait IdentitySwitching
{
    protected readonly bool $switchIdentities;

    private function addSwitchIdentitiesOption(Command $command, bool $defaultEnabled = true): void
    {
        $command->addOption(
            'randomize-identity',
            'r',
            InputOption::VALUE_NEGATABLE,
            'Randomizes HTTP client identity and API keys (if multiple configured)',
            $defaultEnabled
        );
    }

    private function resolveSwitchIdentities(InputInterface $input): void
    {
        $this->switchIdentities = $input->getOption('randomize-identity');
    }
}
