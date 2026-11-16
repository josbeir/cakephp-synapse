<?php
declare(strict_types=1);

namespace TestApp\Mcp;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tool;

/**
 * Host-provided capability resolved through the application container.
 */
#[Name('custom_tool')]
#[Description('A custom application capability.')]
class CustomTool extends Tool
{
    /**
     * @param \TestApp\Mcp\GreetingService $greeting Application service
     */
    public function __construct(private GreetingService $greeting)
    {
    }

    public function handle(): Response
    {
        return Response::text($this->greeting->message());
    }
}
