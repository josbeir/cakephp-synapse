<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\Log\Log;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Synapse\Exception\CapabilityException;
use Throwable;

/**
 * Shared JSON responses and runtime validation for Synapse's scalar tool inputs.
 */
abstract class AbstractTool extends Tool
{
    /**
     * Validate the advertised input schema before invoking a typed service.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @return array<string, mixed>
     * @throws \Crustum\Mcp\Exception\ValidationException
     */
    protected function validatedArguments(Request $request): array
    {
        $schema = $this->toArray()['inputSchema'] ?? [];
        $input = $request->all();
        $arguments = [];
        $errors = [];

        foreach ($schema['properties'] as $name => $property) {
            if (!array_key_exists($name, $input)) {
                if (in_array($name, $schema['required'] ?? [], true)) {
                    $errors[$name][] = sprintf('Argument "%s" is required.', $name);
                }

                $arguments[$name] = $property['default'] ?? null;
                continue;
            }

            $value = $input[$name];
            $types = isset($property['anyOf'])
                ? array_column($property['anyOf'], 'type')
                : (array)$property['type'];
            $valid = match (get_debug_type($value)) {
                'string' => in_array('string', $types, true),
                'int' => in_array('integer', $types, true),
                'bool' => in_array('boolean', $types, true),
                'null' => in_array('null', $types, true),
                default => false,
            };
            if (!$valid) {
                $errors[$name][] = sprintf('Argument "%s" must be %s.', $name, implode(' or ', $types));
            } elseif (isset($property['enum']) && !in_array($value, $property['enum'], true)) {
                $errors[$name][] = sprintf(
                    'Argument "%s" must be one of: %s.',
                    $name,
                    implode(', ', $property['enum']),
                );
            }

            $arguments[$name] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $arguments;
    }

    /**
     * Preserve JSON payloads and keep unexpected implementation errors private.
     *
     * @param callable(): mixed $operation Application operation
     */
    protected function respond(callable $operation): Response
    {
        try {
            return Response::json($operation());
        } catch (CapabilityException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Synapse capability failed: ' . $exception->getMessage());

            return Response::error('The operation failed. Check the application logs for details.');
        }
    }
}
