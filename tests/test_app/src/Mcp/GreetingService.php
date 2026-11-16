<?php
declare(strict_types=1);

namespace TestApp\Mcp;

/**
 * Dependency used to verify host application container bindings.
 */
class GreetingService
{
    public function message(): string
    {
        return 'Hello from the application container';
    }
}
