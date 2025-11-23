<?php
declare(strict_types=1);

namespace Synapse\Mcp;

use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Mcp\Capability\Attribute\McpTool;
use Throwable;

/**
 * Tinker Tools
 *
 * Execute PHP code in the CakePHP application context for debugging and testing.
 */
class TinkerTools
{
    use LocatorAwareTrait;
    use LogTrait;

    /**
     * Execute PHP code in the application context.
     *
     * Similar to bin/cake console, this allows execution of arbitrary PHP code
     * with access to the full CakePHP application context including models,
     * configuration, and helpers. The $this context is available within the
     * executed code, providing access to fetchTable(), log(), and other trait methods.
     *
     * @param string $code PHP code to execute (without opening <?php tags)
     * @param int $timeout Maximum execution time in seconds (default: 30, max: 180)
     * @return array<string, mixed> Execution result with output, return value, and type info
     */
    #[McpTool(
        name: 'tinker',
        description: 'Execute PHP code in the CakePHP application context. ' .
            'Use for debugging, testing code snippets, and exploring the application. ' .
            'DO NOT create/modify data without explicit user approval. ' .
            'Prefer feature tests and existing commands over custom code.',
    )]
    public function execute(string $code, int $timeout = 30): array
    {
        // phpcs:disable Squiz.PHP.Eval.Discouraged
        // Validate timeout bounds
        $timeout = min(max(1, $timeout), 180);

        // Strip PHP tags
        $code = str_replace(['<?php', '<?', '?>'], '', $code);

        // Set memory and time limits
        ini_set('memory_limit', '256M');
        set_time_limit($timeout);

        // Capture output
        ob_start();

        try {
            // Execute code in CakePHP application context
            $result = eval($code);

            $output = ob_get_contents();

            $response = [
                'result' => $result,
                'output' => $output ?: null,
                'type' => get_debug_type($result),
                'success' => true,
            ];

            // Include class name for objects
            if (is_object($result)) {
                $response['class'] = $result::class;
            }

            // Include array count
            if (is_array($result)) {
                $response['count'] = count($result);
            }

            return $response;
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'error' => $throwable->getMessage(),
                'type' => $throwable::class,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ];
        } finally {
            ob_end_clean();
        }

        // phpcs:enable Squiz.PHP.Eval.Discouraged
    }
}
