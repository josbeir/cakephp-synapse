<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Cake\Core\Configure;
use Mcp\Exception\PromptGetException;

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

    /**
     * Validate that a parameter value is one of the allowed values
     *
     * @param string $value The parameter value
     * @param array<string> $allowed Allowed values
     * @param string $paramName The parameter name (for error message)
     * @param string $promptName The prompt name (for error message)
     * @throws \Mcp\Exception\PromptGetException
     */
    protected function validateEnumParameter(
        string $value,
        array $allowed,
        string $paramName,
        string $promptName,
    ): void {
        if (!in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            throw new PromptGetException(
                sprintf("Invalid value for parameter '%s': '%s'. ", $paramName, $value) .
                sprintf('Expected one of: %s. ', $allowedStr) .
                'Prompt: ' . $promptName,
            );
        }
    }

    /**
     * Validate that a parameter is not empty
     *
     * @param string $value The parameter value
     * @param string $paramName The parameter name (for error message)
     * @param string $promptName The prompt name (for error message)
     * @throws \Mcp\Exception\PromptGetException
     */
    protected function validateNonEmptyParameter(
        string $value,
        string $paramName,
        string $promptName,
    ): void {
        if ($value === '' || $value === '0') {
            throw new PromptGetException(
                sprintf("Parameter '%s' cannot be empty. Prompt: %s", $paramName, $promptName),
            );
        }
    }

    /**
     * Validate comma-separated values against allowed list
     *
     * @param string $value Comma-separated values
     * @param array<string> $allowed Allowed values
     * @param string $paramName The parameter name (for error message)
     * @param string $promptName The prompt name (for error message)
     * @throws \Mcp\Exception\PromptGetException
     */
    protected function validateCommaSeparatedParameter(
        string $value,
        array $allowed,
        string $paramName,
        string $promptName,
    ): void {
        if ($value === '' || $value === '0') {
            return;
        }

        $values = array_map('trim', explode(',', $value));
        $invalid = array_diff($values, $allowed);

        if ($invalid !== []) {
            $invalidStr = implode(', ', $invalid);
            $allowedStr = implode(', ', $allowed);
            throw new PromptGetException(
                sprintf("Invalid values for parameter '%s': %s. ", $paramName, $invalidStr) .
                sprintf('Expected one of: %s. ', $allowedStr) .
                'Prompt: ' . $promptName,
            );
        }
    }
}
