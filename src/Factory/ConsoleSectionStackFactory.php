<?php
declare(strict_types=1);

namespace App\Factory;

use App\Struct\OrderedObjectLibrary;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

/**
 * Allows easy handling of Symfony Console sections
 *
 * Sections in Symfony Console are created but never destroyed. Once you create one you cannot "return"/destroy it. This
 * isn't normally an issue when not dealing with a lot of them. However, when a long-running command is supposed to
 * render a few progress bars at a time and do it multiple thousands of times over the runtime... it becomes an issue as
 * every section is ALWAYS rendered once created. This is why sections should be reused.
 * With the number of bugs I fixed over the course of the development of this small and insignificant portion of the app
 * I'm sure this util will be reused ;)
 */
readonly class ConsoleSectionStackFactory
{
    private OrderedObjectLibrary $library;

    /**
     * @return OrderedObjectLibrary<ConsoleSectionOutput>
     */
    public function createForOutput(ConsoleOutputInterface $output): OrderedObjectLibrary
    {
        return new OrderedObjectLibrary(
            borrowOrder: OrderedObjectLibrary::BORROW_BOTTOM_FIRST,
            newElementFactory: fn() => $output->section()
        );
    }
}
