<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * TinkerContext provides the execution context for subprocess tinker evaluation.
 *
 * This class provides the same traits as TinkerTools, allowing code executed
 * in a subprocess to access fetchTable(), log(), etc. via the $context variable.
 *
 * @example
 * ```php
 * // In tinker code (subprocess mode):
 * $users = $context->fetchTable('Users');
 * $context->log('Fetched users table', 'debug');
 * ```
 */
class TinkerContext
{
    use LocatorAwareTrait;
    use LogTrait;
}
