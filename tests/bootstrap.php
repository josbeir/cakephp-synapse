<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Chronos\Chronos;
use Cake\Core\Configure;

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

require_once PLUGIN_ROOT . '/vendor/autoload.php';
require_once CORE_PATH . 'config/bootstrap.php';
require_once CORE_PATH . 'src' . DS . 'Core' . DS . 'functions_global.php';

Configure::write('App', [
    'encoding' => 'UTF-8',
    'namespace' => 'Mercure\TestApp',
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
];

Cache::setConfig($cache);
