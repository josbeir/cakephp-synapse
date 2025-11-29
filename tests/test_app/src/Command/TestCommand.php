<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Test Command
 *
 * A test command with options and arguments for testing purposes.
 */
class TestCommand extends Command
{
    public static function defaultName(): string
    {
        return 'test_command';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('A test command for unit testing');

        $parser->addOption('output-format', [
            'short' => 'o',
            'help' => 'Output format',
            'default' => 'json',
            'choices' => ['json', 'xml', 'yaml'],
        ]);

        $parser->addArgument('name', [
            'help' => 'Name to process',
            'required' => true,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        return static::CODE_SUCCESS;
    }
}
