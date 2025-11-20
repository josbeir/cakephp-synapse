#!/usr/bin/php -q
<?php
declare(strict_types=1);

require __DIR__ . '../../../../vendor/autoload.php';

use Cake\Console\CommandRunner;
use TestApp\Application;

// Build the runner with an application and root executable name.
$runner = new CommandRunner(new Application('../'), 'cake');
exit($runner->run($argv));
