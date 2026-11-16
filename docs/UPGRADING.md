# Upgrading to Synapse 1.0

Synapse 1.0 replaces `mcp/sdk` with `crustum/mcp ^1.3.1`. Crustum owns the MCP
server lifecycle, protocol negotiation, transport, registration, and Inspector.
Synapse retains its 26 tools, 11 prompts, two documentation resource templates,
documentation index, and subprocess behavior.

## Requirements and installation

PHP 8.2 remains supported. CakePHP 5.4 is now the minimum.

The experimental `cake6` branch instead requires PHP 8.5+, CakePHP `6.x-dev`, Crustum MCP `2.x-dev`, and Bake `4.x-dev`. Enable `"minimum-stability": "dev"` with `"prefer-stable": true` in the host application and install `josbeir/cakephp-synapse:dev-cake6` with `--with-all-dependencies`. It uses the same Crustum capabilities and stdio entry point described below. Update custom container imports to `Cake\Container` and custom commands to CakePHP 6's parameterless `execute()` API using `$this->args` and `$this->io`.
Install Synapse as a development dependency and
let Composer validate the required extensions. Crustum's OAuth dependency needs
`ext-sodium` on PHP 8.5, even when only stdio is used.

Crustum 1.3.1 unconditionally registers Bake commands when its console hook runs.
Synapse therefore requires `cakephp/bake ^3.2` so native command registration works
in applications that have not separately installed Bake. There is no requirement
to load Bake or Tessera as application plugins or install OAuth tables.

Bake 3.2 is the lowest stable version resolving with this dependency set. The
Symfony Filesystem 7.x minimum is now 7.4.23 because Composer blocks the old
minimum under advisory `PKSA-g499-cm4q-v35y`. Composer's security protections
remain enabled.

Load the plugins in this order in the application's `config/plugins.php`:

```php
return [
    'Crustum/Mcp' => [
        'onlyCli' => true,
        'optional' => true,
        'routes' => false,
        'middleware' => false,
    ],
    'Synapse' => ['onlyCli' => true, 'optional' => true],
];
```

For programmatic loading, use `$this->addPlugin('Crustum/Mcp', ['routes' => false,
'middleware' => false])` before `$this->addPlugin('Synapse')` in the application's
CLI plugin-loading branch. Use the full `Crustum/Mcp` name; it also identifies
the plugin during configuration loading. Loading only its class with the short
default name can cause CakePHP to load another instance while resolving config.

This setup registers no HTTP routes or middleware. HTTP transport, OAuth, remote
authorization, MCP Apps, and searchable tool catalogs are outside this release.

## Commands and client configuration

| Previous command | Replacement |
| --- | --- |
| `bin/cake synapse server` | `bin/cake mcp start synapse` |
| `bin/cake synapse server --inspect` | `bin/cake mcp inspector synapse` |
| `--transport`, `--no-cache`, `--clear-cache` | Removed; no scanning or discovery cache |

Update IDE and desktop MCP launch arguments to `["mcp", "start", "synapse"]`.
Run from the CakePHP application directory, or use absolute executable paths and
the client's working-directory setting. Inspector requires Node.js/npx.

The index, search, and internal Tinker evaluation commands are unchanged.
Documentation databases and checkout paths are compatible; reindexing is not
required by this migration. Normal application caches are not cleared.

## Configuration and PHP interfaces

| Previous setting or interface | Version 1.0 |
| --- | --- |
| `Synapse.serverInfo`, `Synapse.instructions` | Preserved |
| `Synapse.documentation`, `Synapse.tinker`, `Synapse.prompts` | Preserved |
| `Synapse.discovery.*`, `Synapse.basePath` | Removed; explicit capability classes |
| `Synapse.protocolVersion` / `MCP_PROTOCOL_VERSION` | Removed; Crustum handles protocol versions |
| `Synapse.logger` / `Log.synapse` server wiring | Removed; configure CakePHP file/stderr logging |
| SDK attributes on application methods | Crustum classes registered in `Synapse.capabilities` |
| `Synapse\Builder\ServerBuilder` and `Synapse\Command\ServerCommand` | Removed; `Synapse\Server\SynapseServer` and Crustum commands |
| Grouped `Synapse\Tools\*Tools` PHP classes | Internal behavior moved to `Synapse\Service\*Service`; MCP classes represent individual operations |
| Prompt `handle(string ...)` | `handle(Request)`; typed rendering remains available as `render(string ...)` |
| SDK exceptions/content objects | Crustum responses and validation errors |

`Synapse.capabilities.tools`, `.prompts`, and `.resources` are lists of additional
Crustum capability classes. They append to the built-ins. Invalid classes,
unresolvable dependencies, and duplicate public names fail at startup.
`Synapse.prompts` continues to contain prompt configuration, not a class list.

Synapse registers `Mcp.local.synapse` only when the host has not provided it.
Hosts can replace that mapping with their own server class. Other MCP servers
and application MCP settings remain intact.

Tool names, argument names/defaults, existing JSON payloads, prompt names and
content, and resource URI templates are preserved. Tool failures are actionable
MCP error results; invalid prompt arguments use Crustum's validation error
mapping. Error wording and framework-added response metadata may differ.
Unexpected implementation failures are logged and return generic messages.

Nullable tool filters advertise `anyOf` branches for `string` and `null` to
improve schema portability. Omitting a filter and passing explicit `null` retain
their existing behavior.

## Custom capabilities

Convert each old SDK attribute method into a Crustum `Tool`, `Prompt`, or
`Resource` class. Use Crustum's supported attributes and explicit input schemas;
validate again in the handler. There is no compatibility scanner for SDK classes.

For example, add `src/Service/ApplicationInfo.php` to the host application:

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

class ApplicationInfo
{
    public function name(): string
    {
        return (string)Configure::read('App.name', 'CakePHP Application');
    }
}
```

Add `src/Mcp/Tools/ApplicationInfoTool.php`:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Service\ApplicationInfo;
use Cake\Validation\Validator;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('application_info')]
#[Description('Read the application display name with a caller-provided label. No side effects.')]
#[IsReadOnly]
class ApplicationInfoTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'label' => $schema->string()
                ->description('Non-empty label to include with the application name.')
                ->min(1)
                ->required(),
        ];
    }

    public function handle(Request $request, ApplicationInfo $info): Response
    {
        $validator = new Validator();
        $validator->requirePresence('label')
            ->add('label', 'string', [
                'rule' => is_string(...),
                'message' => 'label must be a string.',
            ])
            ->notEmptyString('label', 'Provide a non-empty label.');
        $arguments = $request->validateWith($validator);

        return Response::json(['label' => $arguments['label'], 'name' => $info->name()]);
    }
}
```

Register the service in `Application::services()`:

```php
public function services(\Cake\Core\ContainerInterface $container): void
{
    $container->addShared(\App\Service\ApplicationInfo::class);
}
```

Then add the capability to application configuration:

```php
return [
    'Synapse' => [
        'capabilities' => [
            'tools' => [\App\Mcp\Tools\ApplicationInfoTool::class],
            'prompts' => [],
            'resources' => [],
        ],
    ],
];
```

Use the corresponding `Prompt` and `Resource` base classes for other primitives.
Dynamic resources implement `HasUriTemplate`. Register a capability itself in
the container when it needs constructor dependencies. Conditional registration
through `shouldRegister()` is respected for both listing and invocation.

Refer to [Crustum's integration guide](https://github.com/Crustum/mcp/blob/main/docs/index.md)
for prompt arguments, responses, resource templates, and container injection.
