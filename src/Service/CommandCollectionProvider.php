<?php
declare(strict_types=1);

namespace Synapse\Service;

use Cake\Console\CommandCollection;

/**
 * Holds the host application's current console commands without global state.
 */
class CommandCollectionProvider
{
    /**
     * @param \Cake\Console\CommandCollection $commands Initial command collection
     */
    public function __construct(private CommandCollection $commands = new CommandCollection())
    {
    }

    /**
     * Capture the fully built application command collection.
     *
     * @param \Cake\Console\CommandCollection $commands Application commands
     */
    public function set(CommandCollection $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * Get commands, including when the service was resolved before the event.
     */
    public function get(): CommandCollection
    {
        return $this->commands;
    }
}
