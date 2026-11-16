<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Synapse\Exception\CapabilityException;
use Synapse\Resources\DocumentationContentResource;
use Synapse\Resources\DocumentationSearchResource;
use Synapse\Server\SynapseServer;
use Synapse\Service\DocumentationResourceService;
use Synapse\Test\TestCase\McpTestCase;

/**
 * Verifies the expected and unexpected failure shapes at the resource boundary.
 */
class DocumentationResourceAdaptersTest extends McpTestCase
{
    /**
     * @return array<string, array{class-string<\Crustum\Mcp\Server\Resource>, non-empty-string, string}>
     */
    public static function resourceProvider(): array
    {
        return [
            'search' => [DocumentationSearchResource::class, 'searchResource', 'query'],
            'content' => [DocumentationContentResource::class, 'contentResource', 'docId'],
        ];
    }

    /**
     * @param class-string<\Crustum\Mcp\Server\Resource> $resource Resource class
     * @param non-empty-string $method Service method
     * @param string $argument URI template variable
     */
    #[DataProvider('resourceProvider')]
    public function testExpectedFailureIsActionable(string $resource, string $method, string $argument): void
    {
        $service = $this->createMock(DocumentationResourceService::class);
        $service->expects($this->once())->method($method)->with('example')
            ->willThrowException(new CapabilityException('Documentation is not indexed.'));
        $this->container->add(DocumentationResourceService::class, $service);

        SynapseServer::resource($resource, [$argument => 'example'])
            ->assertHasErrors(['Documentation is not indexed.']);
    }

    /**
     * @param class-string<\Crustum\Mcp\Server\Resource> $resource Resource class
     * @param non-empty-string $method Service method
     * @param string $argument URI template variable
     */
    #[DataProvider('resourceProvider')]
    public function testUnexpectedFailureDoesNotExposeDetails(string $resource, string $method, string $argument): void
    {
        $service = $this->createMock(DocumentationResourceService::class);
        $service->expects($this->once())->method($method)->with('example')
            ->willThrowException(new RuntimeException('sensitive implementation detail'));
        $this->container->add(DocumentationResourceService::class, $service);

        SynapseServer::resource($resource, [$argument => 'example'])
            ->assertHasErrors(['The documentation could not be read. Check the application logs for details.']);
    }
}
