<div v-pre>

# Custom Nodes

Create your own node types to extend the workflow engine with custom logic.

## Creating a Node

Create a class that implements `NodeInterface` (or extends `BaseNode`) and add the `#[AsWorkflowNode]` attribute:

```php
<?php

namespace App\Workflow\Nodes;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;

#[AsWorkflowNode(key: 'slack_message', type: NodeType::Action, label: 'Slack Message')]
class SlackMessageNode extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'channel', 'type' => 'string', 'label' => 'Channel', 'required' => true, 'supports_expression' => true],
            ['key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true, 'supports_expression' => true],
            ['key' => 'webhook_url', 'type' => 'string', 'label' => 'Webhook URL', 'required' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'slack_sent', 'type' => 'boolean', 'label' => 'Slack Sent'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                \Illuminate\Support\Facades\Http::post($config['webhook_url'], [
                    'channel' => $config['channel'],
                    'text'    => $config['message'],
                ]);

                $results[] = array_merge($item, ['slack_sent' => true]);
            } catch (\Throwable $e) {
                return NodeOutput::ports([
                    'main'  => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }
}
```

## The AsWorkflowNode Attribute

```php
#[AsWorkflowNode(
    key: 'slack_message',       // Unique identifier used in addNode()
    type: NodeType::Action,     // Category for UI grouping
    label: 'Slack Message',     // Human-readable label
)]
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `key` | string | Unique key for this node type |
| `type` | NodeType | `Trigger`, `Action`, `Condition`, `Transformer`, `Control`, `Utility`, or `Code` |
| `label` | string | Display name |

## NodeInterface

Every node must implement `NodeInterface`:

```php
interface NodeInterface
{
    public function inputPorts(): array;    // e.g. ['main']
    public function outputPorts(): array;   // e.g. ['main', 'error']
    public static function configSchema(): array;
    public static function outputSchema(): array;
    public function execute(NodeInput $input, array $config): NodeOutput;
}
```

The `BaseNode` class provides sensible defaults: input `['main']`, output `['main', 'error']`, an empty config schema, and an empty output schema.

## NodeInput

```php
class NodeInput
{
    public readonly array $items;              // Array of items to process
    public readonly ExecutionContext $context;  // Run context (IDs, outputs)
}
```

## NodeOutput

Create output using static methods:

```php
// Send all items to the 'main' port
NodeOutput::main($items);

// Send items to a specific port
NodeOutput::port('custom_port', $items);

// Send items to multiple ports
NodeOutput::ports([
    'main'  => $successItems,
    'error' => $errorItems,
]);
```

## Output Schema

The output schema declares what variables a node produces. This powers the visual editor's autocomplete and variable panel, helping users discover available variables when writing expressions.

Each key in the returned array is a port name, and the value is an array of field definitions:

```php
public static function outputSchema(): array
{
    return [
        'main' => [
            ['key' => 'slack_sent', 'type' => 'boolean', 'label' => 'Slack Sent'],
            ['key' => 'channel', 'type' => 'string', 'label' => 'Channel Name'],
        ],
    ];
}
```

Downstream nodes will see these as `{{ nodes.Slack Message.main.0.slack_sent }}` in the autocomplete.

**Field definition:**

| Property | Type | Description |
| --- | --- | --- |
| `key` | string | Dot-notation path of the output field |
| `type` | string | Data type: `string`, `integer`, `boolean`, `object`, `array`, `mixed` |
| `label` | string | Human-readable label shown in the editor |

For nodes with dynamic output (e.g. Set Fields), you can return a wildcard marker:

```php
return [
    'main' => [
        ['key' => '*', 'type' => 'mixed', 'label' => 'Dynamic fields from config'],
    ],
];
```

`BaseNode` returns an empty output schema by default. Override it in your node to enable variable discovery.

## Config Schema

The config schema defines what fields appear in the visual editor and validates configuration:

```php
public static function configSchema(): array
{
    return [
        [
            'key'                 => 'field_name',
            'type'                => 'string',
            'label'               => 'Display Label',
            'required'            => true,
            'supports_expression' => true,
            'description'         => 'Help text below the field',
            'placeholder'         => 'Placeholder text',
        ],
    ];
}
```

### Field Types

| Type | Description | Extra Properties |
|------|-------------|------------------|
| `string` | Single-line text input | `placeholder`, `supports_expression` |
| `textarea` | Multi-line text input | `placeholder`, `supports_expression` |
| `integer` | Integer number input | `placeholder` |
| `number` | Float number input | `min`, `max`, `step`, `placeholder` |
| `boolean` | Toggle switch | — |
| `select` | Dropdown | `options`, `depends_on`, `options_map` |
| `multiselect` | Multi-selection | `options`, `options_from` |
| `json` | JSON editor with validation | — |
| `keyvalue` | Dynamic key-value pairs | — |
| `array_of_objects` | Repeatable nested groups | `schema` (nested field definitions) |
| `model_select` | Eloquent model picker | — |
| `url` | URL input with validation | `placeholder`, `supports_expression` |
| `password` | Masked input with show/hide toggle | `placeholder` |
| `color` | Color picker with hex input | `placeholder` |
| `slider` | Range slider | `min`, `max`, `step` |
| `code` | Monospace code editor | `language`, `placeholder`, `supports_expression` |
| `info` | Read-only information text (not a form field) | `description` |
| `section` | Collapsible section heading | `collapsible`, `collapsed` |
| `custom` | Web Component (see [Plugin System](/advanced/plugins)) | `custom_component` |

### Field Properties

| Property | Type | Description |
|----------|------|-------------|
| `key` | string | Field identifier, used as the config key |
| `type` | string | One of the field types above |
| `label` | string | Display label |
| `required` | boolean | Whether the field is required |
| `supports_expression` | boolean | Allow `{{ }}` template syntax |
| `description` | string | Help text shown below the field |
| `placeholder` | string | Input placeholder text |
| `options` | string[] | Static options for `select` / `multiselect` |
| `depends_on` | string | Key of parent field for dynamic options |
| `options_map` | Record | Options per parent value: `{'parent_value': ['opt1', 'opt2']}` |
| `show_when` | object | Conditional visibility: `{'key': 'field', 'value': 'expected'}` |
| `schema` | array | Nested field definitions for `array_of_objects` |
| `min` / `max` / `step` | number | For `number`, `slider` types |
| `language` | string | Language hint for `code` type |
| `collapsible` / `collapsed` | boolean | For `section` type |
| `readonly` | boolean | Disable editing |

### Sections and Layout

Group related fields with `section`:

```php
public static function configSchema(): array
{
    return [
        ['key' => '_auth', 'type' => 'section', 'label' => 'Authentication', 'collapsible' => true],
        ['key' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true, 'description' => 'Get your key from Settings → API'],
        ['key' => 'secret', 'type' => 'password', 'label' => 'Secret'],

        ['key' => '_settings', 'type' => 'section', 'label' => 'Settings', 'collapsible' => true],
        ['key' => 'color', 'type' => 'color', 'label' => 'Brand Color'],
        ['key' => 'rate', 'type' => 'slider', 'label' => 'Rate Limit', 'min' => 1, 'max' => 100, 'step' => 1],
    ];
}
```

### Dependent Select

Make a select's options change based on another field:

```php
['key' => 'provider', 'type' => 'select', 'label' => 'Provider', 'options' => ['openai', 'anthropic']],
['key' => 'model', 'type' => 'select', 'label' => 'Model', 'depends_on' => 'provider', 'options_map' => [
    'openai'    => ['gpt-4.1', 'gpt-4o', 'o3-mini'],
    'anthropic' => ['claude-sonnet-4-5-20250514', 'claude-haiku-4-5-20251001'],
]],
```

### Conditional Visibility

Show/hide fields based on other field values:

```php
['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['inline', 'template']],
['key' => 'body', 'type' => 'textarea', 'label' => 'Body', 'show_when' => ['key' => 'mode', 'value' => 'inline']],
['key' => 'template_id', 'type' => 'string', 'label' => 'Template ID', 'show_when' => ['key' => 'mode', 'value' => 'template']],
```

## Registering Custom Nodes

### Auto-Discovery

Add your node directory to the config:

```php
// config/workflow-automation.php
'node_discovery' => [
    'app_paths' => [
        app_path('Workflow/Nodes'),
    ],
],
```

The package scans these directories for classes with the `#[AsWorkflowNode]` attribute.

### Manual Registration

Register in a service provider:

```php
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

public function boot(): void
{
    $registry = app(NodeRegistry::class);
    $registry->discoverNodes(app_path('Workflow/Nodes'));
}
```

## Creating a Trigger

Triggers implement `TriggerInterface` instead of `NodeInterface`:

```php
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;

#[AsWorkflowNode(key: 'my_trigger', type: NodeType::Trigger, label: 'My Trigger')]
class MyTrigger implements TriggerInterface
{
    public function inputPorts(): array { return []; }      // Triggers have no input
    public function outputPorts(): array { return ['main']; }

    public static function configSchema(): array { return []; }

    public static function outputSchema(): array { return []; }

    public function register(int $workflowId, int $nodeId, array $config): void
    {
        // Called when the workflow is activated
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void
    {
        // Called when the workflow is deactivated
    }

    public function extractPayload(mixed $event): array
    {
        // Convert the triggering event to an items array
        return is_array($event) ? $event : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
```

## Using Your Custom Node

```php
$workflow = Workflow::create(['name' => 'Alert Pipeline']);

$trigger = $workflow->addNode('New Alert', 'manual');
$slack   = $workflow->addNode('Notify Team', 'slack_message', [
    'channel'     => '#alerts',
    'message'     => 'Alert: {{ item.message }}',
    'webhook_url' => 'https://hooks.slack.com/services/...',
]);

$trigger->connect($slack);
$workflow->activate();
```

## Node Documentation

Add documentation that appears in the visual editor's **Docs** tab when users select your node. Override the `documentation()` method on your node class:

```php
#[AsWorkflowNode(key: 'slack_message', type: NodeType::Action, label: 'Slack Message')]
class SlackMessageNode extends BaseNode
{
    public static function documentation(): ?string
    {
        return file_get_contents(__DIR__.'/../docs/slack-message.md');
    }
}
```

You can also return inline markdown:

```php
public static function documentation(): ?string
{
    return <<<'MD'
    # Slack Message

    Sends a message to a Slack channel via webhook.

    ## Config

    | Key | Type | Required | Description |
    |-----|------|----------|-------------|
    | channel | string | Yes | Target channel name |
    | message | textarea | Yes | Message body (supports expressions) |
    | webhook_url | string | Yes | Slack incoming webhook URL |

    ## Tips

    - Use `{{ }}` expressions in the message field to include dynamic data
    - The node outputs `slack_sent: true` on success
    MD;
}
```

`BaseNode` provides a default implementation that automatically loads the matching markdown file from the package's `docs/` directory (e.g. `docs/nodes/slack-message.md` for key `slack_message`). If no file exists, it returns `null` and the Docs tab is hidden.

## Dependency Injection

Custom nodes support constructor injection from the Laravel container:

```php
#[AsWorkflowNode(key: 'ai_classify', type: NodeType::Action, label: 'AI Classify')]
class AiClassifyNode extends BaseNode
{
    public function __construct(
        private readonly MyAiService $ai,
    ) {}

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];
        foreach ($input->items as $item) {
            $category = $this->ai->classify($item['text']);
            $results[] = array_merge($item, ['category' => $category]);
        }
        return NodeOutput::main($results);
    }
}
```

</div>
