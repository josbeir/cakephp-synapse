<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Cake\Core\Configure;

/**
 * Abstract Prompt Base Class
 *
 * Provides shared configuration and utilities for all MCP prompts.
 */
abstract class AbstractPrompt
{
    /**
     * CakePHP version to reference in prompts
     */
    protected string $cakephpVersion;

    /**
     * PHP version to reference in prompts
     */
    protected string $phpVersion;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->phpVersion = Configure::read('Synapse.prompts.php_version', '8.2');
    }
}
