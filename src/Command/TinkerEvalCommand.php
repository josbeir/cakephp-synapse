<?php
declare(strict_types=1);

namespace Synapse\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Synapse\Tools\TinkerContext;
use Throwable;

/**
 * TinkerEval Command
 *
 * Executes PHP code in a fresh CakePHP application context.
 * This command is designed to be called as a subprocess by TinkerTools
 * to ensure code changes are reflected without restarting the server.
 *
 * Code is received via stdin and results are returned as JSON to stdout.
 */
class TinkerEvalCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'synapse tinker_eval';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Execute PHP code in a fresh CakePHP application context (internal)';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Execute PHP code in the CakePHP application context. ' .
                'This command is intended for internal use by TinkerTools subprocess execution.',
            )
            ->addOption('timeout', [
                'short' => 't',
                'help' => 'Maximum execution time in seconds',
                'default' => '30',
            ]);

        return $parser;
    }

    /**
     * Execute the command
     *
     * Reads PHP code from stdin, executes it with access to a TinkerContext,
     * and outputs results as JSON to stdout.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @return int Exit code
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // phpcs:disable Squiz.PHP.Eval.Discouraged

        // Read code from stdin
        $code = file_get_contents('php://stdin');

        if ($code === false || trim($code) === '') {
            $this->outputJson($io, [
                'success' => false,
                'error' => 'No code provided via stdin',
                'type' => 'InvalidArgumentException',
            ]);

            return static::CODE_ERROR;
        }

        // Parse and validate timeout
        $timeout = (int)$args->getOption('timeout');
        $timeout = min(max(1, $timeout), 180);

        // Set execution limits
        ini_set('memory_limit', '256M');
        set_time_limit($timeout);

        // Strip PHP tags from code
        $code = str_replace(['<?php', '<?', '?>'], '', $code);

        // Create the context object that provides fetchTable(), log(), etc.
        // Note: $context is used inside eval() - see rector.php for skip rule
        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
        $context = new TinkerContext();

        // Capture output
        ob_start();

        try {
            // Execute code with $context available
            // The $context variable is explicitly made available to the eval'd code
            $result = eval($code);

            $output = ob_get_contents();

            $response = [
                'success' => true,
                'result' => $this->serializeResult($result),
                'output' => $output ?: null,
                'type' => get_debug_type($result),
            ];

            // Include class name for objects
            if (is_object($result)) {
                $response['class'] = $result::class;
            }

            // Include array count
            if (is_array($result)) {
                $response['count'] = count($result);
            }

            $this->outputJson($io, $response);

            return static::CODE_SUCCESS;
        } catch (Throwable $throwable) {
            ob_end_clean();

            $this->outputJson($io, [
                'success' => false,
                'error' => $throwable->getMessage(),
                'type' => $throwable::class,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            return static::CODE_ERROR;
        } finally {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // phpcs:enable Squiz.PHP.Eval.Discouraged
    }

    /**
     * Serialize a result value for JSON output.
     *
     * Handles objects by converting them to array representation
     * since many objects aren't directly JSON serializable.
     *
     * @param mixed $result The result to serialize
     * @return mixed Serialized result
     */
    private function serializeResult(mixed $result): mixed
    {
        if ($result === null || is_scalar($result)) {
            return $result;
        }

        if (is_array($result)) {
            return array_map([$this, 'serializeResult'], $result);
        }

        if (is_object($result)) {
            // Try to convert to array if possible
            if (method_exists($result, 'toArray')) {
                return $result->toArray();
            }

            if (method_exists($result, 'jsonSerialize')) {
                return $result->jsonSerialize();
            }

            if (method_exists($result, '__toString')) {
                return (string)$result;
            }

            // Fallback: return class info and public properties
            return [
                '__class' => $result::class,
                '__properties' => get_object_vars($result),
            ];
        }

        if (is_resource($result)) {
            return [
                '__type' => 'resource',
                '__resource_type' => get_resource_type($result),
            ];
        }

        return null;
    }

    /**
     * Output JSON response to stdout.
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @param array<string, mixed> $data Data to output as JSON
     */
    private function outputJson(ConsoleIo $io, array $data): void
    {
        // Use out() with no newline formatting, raw output
        $io->out(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
