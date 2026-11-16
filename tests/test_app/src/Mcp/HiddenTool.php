<?php
declare(strict_types=1);

namespace TestApp\Mcp;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tool;

/**
 * A tool which must be excluded from both discovery and invocation.
 */
#[Name('hidden_tool')]
class HiddenTool extends Tool
{
    public function shouldRegister(): bool
    {
        return false;
    }

    public function handle(): Response
    {
        return Response::text('This tool is disabled.');
    }
}
