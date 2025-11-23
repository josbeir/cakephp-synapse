<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
use Synapse\Documentation\SearchEngine;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

define('PLUGIN_ROOT', dirname(__DIR__));
define('ROOT', PLUGIN_ROOT . DS . 'tests' . DS . 'test_app');
define('TMP', PLUGIN_ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('APP', ROOT . DS . 'src' . DS);
define('APP_DIR', 'src');
define('CAKE_CORE_INCLUDE_PATH', PLUGIN_ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . APP_DIR . DS);
define('WWW_ROOT', PLUGIN_ROOT . DS . 'webroot' . DS);
define('TESTS', __DIR__ . DS);
define('CONFIG', TESTS . 'config' . DS);
define('TEST_SEARCH_DB', TMP . 'tests' . DS . 'search.db');

require_once PLUGIN_ROOT . '/vendor/autoload.php';
require_once CORE_PATH . 'config/bootstrap.php';
require_once CORE_PATH . 'src' . DS . 'Core' . DS . 'functions_global.php';

Configure::write('App', [
    'encoding' => 'UTF-8',
    'namespace' => 'TestApp',
    'defaultLocale' => 'en_US',
    'fullBaseUrl' => 'http://localhost',
    'paths' => [
        'plugins' => [ROOT . 'plugins' . DS],
        'templates' => [ROOT . DS . 'templates' . DS],
        'locales' => [ROOT . DS . 'resources' . DS . 'locales' . DS],
    ],
]);

Configure::write('debug', true);
Chronos::setTestNow(Chronos::now());

if (!is_dir(TMP)) {
    mkdir(TMP, 0770, true);
}

if (!is_dir(CACHE)) {
    mkdir(CACHE, 0770, true);
}

$cache_key = '_cake_translations_';
if (Configure::version() <= '5.1.0') {
    $cache_key = '_cake_core_';
}

$cache = [
    'default' => [
        'engine' => 'File',
    ],
    $cache_key => [
        'className' => 'File',
        'prefix' => '_cake_core_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => '_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
];

Cache::setConfig($cache);

// Configure test database connections using SQLite
ConnectionManager::setConfig('test', [
    'className' => Connection::class,
    'driver' => Sqlite::class,
    'database' => ':memory:',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

ConnectionManager::setConfig('default', [
    'className' => Connection::class,
    'driver' => Sqlite::class,
    'database' => ':memory:',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

// Load test database schema
(new SchemaLoader())->loadSqlFiles(TESTS . 'schema.sql', 'test');

/**
 * Lock helper to safely manage search engine operations in parallel environments
 *
 * @param callable $callback The operation to perform
 * @return mixed The result of the callback
 */
function withSearchEngineLock(callable $callback): mixed
{
    $lockFile = TMP . 'tests' . DS . 'search.db.lock';
    $lockDir = dirname($lockFile);

    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0770, true);
    }

    $lock = fopen($lockFile, 'c');
    if ($lock === false) {
        return $callback();
    }

    if (!flock($lock, LOCK_EX)) {
        fclose($lock);

        return $callback();
    }

    try {
        return $callback();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

// Clean up test search database before tests run
withSearchEngineLock(function (): void {
    (new SearchEngine(TEST_SEARCH_DB))->destroy();
});
