<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Another Test Command
 *
 * Another test command with arguments for testing purposes.
 */
class AnotherTestCommand extends Command
{
    public static function defaultName(): string
    {
        return 'another_test';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Another test command for unit testing');

        $parser->addArgument('name', [
            'help' => 'Name argument',
            'required' => true,
        ]);

        $parser->addArgument('tags', [
            'help' => 'Optional tags',
            'required' => false,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        return static::CODE_SUCCESS;
    }
}
