<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Synapse\SynapsePlugin;

/**
 * @extends BaseApplication<mixed>
 */
class Application extends BaseApplication
{
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    public function bootstrap(): void
    {
        if (!defined('APP')) {
            parent::bootstrap();
        }

        $this->addPlugin(SynapsePlugin::class);
    }

    public function routes(RouteBuilder $routes): void
    {
    }
}
