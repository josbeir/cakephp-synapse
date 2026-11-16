<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Cake\Core\Configure;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Request;
use Crustum\Mcp\Server\Prompt;

/**
 * Abstract Prompt Base Class
 *
 * Provides shared configuration and utilities for all MCP prompts.
 */
abstract class AbstractPrompt extends Prompt
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
        $this->cakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '6.x');
        $this->phpVersion = Configure::read('Synapse.prompts.php_version', '8.5');
    }

    /**
     * Validate that a parameter value is one of the allowed values
     *
     * @param string $value The parameter value
     * @param array<string> $allowed Allowed values
     * @param string $paramName The parameter name (for error message)
     * @param string $promptName The prompt name (for error message)
     * @throws \Crustum\Mcp\Exception\ValidationException
     */
    protected function validateEnumParameter(
        string $value,
        array $allowed,
        string $paramName,
        string $promptName,
    ): void {
        if (!in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            $this->invalidArgument(
                $paramName,
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
     * @throws \Crustum\Mcp\Exception\ValidationException
     */
    protected function validateNonEmptyParameter(
        string $value,
        string $paramName,
        string $promptName,
    ): void {
        if ($value === '' || $value === '0') {
            $this->invalidArgument(
                $paramName,
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
     * @throws \Crustum\Mcp\Exception\ValidationException
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
            $this->invalidArgument(
                $paramName,
                sprintf("Invalid values for parameter '%s': %s. ", $paramName, $invalidStr) .
                sprintf('Expected one of: %s. ', $allowedStr) .
                'Prompt: ' . $promptName,
            );
        }
    }

    /**
     * Validate string arguments before defaults and prompt-specific validation.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @return array<string, string>
     */
    protected function validatedArguments(Request $request): array
    {
        $input = $request->all();
        $arguments = [];
        foreach ($this->arguments() as $argument) {
            if (!array_key_exists($argument->name, $input)) {
                if ($argument->required) {
                    $this->invalidArgument($argument->name, sprintf('Argument "%s" is required.', $argument->name));
                }

                continue;
            }

            $value = $input[$argument->name];
            if (!is_string($value) || ($argument->required && trim($value) === '')) {
                $this->invalidArgument(
                    $argument->name,
                    sprintf('Argument "%s" must be a non-empty string.', $argument->name),
                );
            }

            $arguments[$argument->name] = $value;
        }

        return $arguments;
    }

    /**
     * Report an invalid prompt argument using Crustum's protocol error mapping.
     *
     * @param string $field Argument name
     * @param string $message Actionable validation message
     */
    protected function invalidArgument(string $field, string $message): never
    {
        throw new ValidationException([$field => [$message]], $message);
    }
}
