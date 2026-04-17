<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Prompts;

use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('workflow_builder')]
#[Title('Workflow Builder Guide')]
#[Description('A comprehensive guide for building workflows. Explains node types, ports, expressions, and the recommended step-by-step process.')]
final class WorkflowBuilderPrompt extends Prompt
{
    public function __construct(
        protected NodeRegistry $registry,
    ) {}

    public static function buildSystemPromptText(array $registryNodes): string
    {
        $nodeList = self::buildNodeList($registryNodes);

        return <<<PROMPT
        You are building workflows for a Laravel application using a graph-based workflow automation engine. Workflows are directed graphs where nodes perform actions and edges define execution order. Each workflow needs at least one trigger node.

        ## Available Node Types

        {$nodeList}

        ## Port System

        Ports define how data flows between nodes. When connecting nodes with connect_nodes, you specify source_port and target_port.

        Common port patterns:
        - Most nodes: input "main", output "main" (and "error" for nodes extending BaseNode)
        - IF Condition: input "main", outputs "true" and "false" — items are routed based on the condition
        - Switch: input "main", outputs "case_*" (dynamic, defined in config) and "default"
        - Loop: input "main", outputs "loop_item" (each iteration) and "loop_done" (after all items)
        - Error Handler: input "main", outputs "notify", "retry", "ignore", "stop"
        - Trigger nodes: no input ports, output "main"

        ## Expression Engine

        Use expressions in any config field marked with supports_expression. Expressions are enclosed in {{ }}.

        Syntax:
        - Access current item fields: {{ item.field_name }}
        - Access nested fields: {{ item.user.email }}
        - Access trigger data: {{ trigger.0.name }}
        - Access other node output: {{ node.{node_id}.main.0.field }}
        - String concatenation: {{ "Hello " ~ item.name }}
        - Ternary: {{ item.age >= 18 ? "adult" : "minor" }}
        - Comparisons: ==, !=, >, <, >=, <=
        - Logical: &&, ||, !
        - Math: +, -, *, /, %
        - Functions: upper(), lower(), length(), join(), split(), trim(), abs(), round(), now(), date_format(), contains(), starts_with(), ends_with(), default()

        ## Config Field Types

        - `string`: plain text value, if `supports_expression` is true you can use `{{ item.field }}` syntax
        - `select`: choose one value from the `options` list
        - `multiselect`: choose multiple values from `options` (pass as array)
        - `model_select`: a fully-qualified Laravel model class name, e.g. `"App\\Models\\User"`, `"App\\Models\\Order"`
        - `boolean`: true or false
        - `integer`: a number
        - `json`: a JSON string or object
        - `textarea`: multi-line text (supports expressions if marked)
        - `keyvalue`: an object of key-value pairs, e.g. `{"Content-Type": "application/json"}`
        - `array`: an array of strings
        - `code`: an expression string (NOT raw PHP), e.g. `"{{ item.price * item.quantity }}"`
        - `expression`: an expression string using `{{ }}` syntax

        ## Best Practices

        - Give nodes meaningful names that describe their purpose
        - Every workflow must start with exactly one trigger node
        - Connect the "error" port to an error_handler node for robust workflows
        - Use set_fields to reshape data between nodes when needed
        - For conditional branching, prefer if_condition for binary choices and switch for multiple cases
        - Use expressions like {{ node.{node_id}.main.0.field }} to reference output from upstream nodes
        - IMPORTANT: Always fill in required config fields when adding nodes. Never leave required fields empty.
        - For model_event triggers: always set `model` to a fully-qualified class like `"App\\Models\\User"` and `events` to an array like `["created"]`, `["updated"]`, etc.
        - For send_mail: always set `send_mode` to `"inline"` (or `"mailable"`), plus `to`, `subject`, `body` fields
        - For http_request: always set `url` and `method`
        - For if_condition: always set `field` (an expression), `operator` (e.g. `"=="`, `"!="`, `">"`, `"contains"`), and optionally `value`
        PROMPT;
    }

    public function arguments(): array
    {
        return [
            new Argument(
                name: 'goal',
                description: "What the workflow should accomplish, e.g. 'Send welcome email when user registers'",
                required: false,
            ),
        ];
    }

    public function handle(Request $request): array
    {
        $nodes = $this->registry->all();
        $system = self::buildSystemPromptText($nodes);

        $messages = [
            Response::text($system)->asAssistant(),
        ];

        $goal = $request->get('goal');

        if ($goal) {
            $messages[] = Response::text("Build a workflow that: {$goal}");
        }

        return $messages;
    }

    protected static function buildNodeList(array $nodes): string
    {
        $categories = [
            'trigger' => [],
            'action' => [],
            'condition' => [],
            'transformer' => [],
            'control' => [],
            'utility' => [],
            'code' => [],
        ];

        foreach ($nodes as $node) {
            $type = $node['type'];
            $ports = implode(', ', $node['output_ports']);
            $line = "  - **{$node['key']}** ({$node['label']}) — outputs: {$ports}";

            // Add config schema details
            $configFields = self::formatConfigSchema($node['config_schema'] ?? []);
            if ($configFields) {
                $line .= "\n    Config: {$configFields}";
            }

            // Add output schema details
            $outputFields = self::formatOutputSchema($node['output_schema'] ?? []);
            if ($outputFields) {
                $line .= "\n    Output: {$outputFields}";
            }

            $categories[$type][] = $line;
        }

        $sections = [];

        foreach ($categories as $type => $lines) {
            if (empty($lines)) {
                continue;
            }

            $heading = ucfirst($type).'s';
            $sections[] = "### {$heading}\n".implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }

    protected static function formatConfigSchema(array $schema): string
    {
        if (empty($schema)) {
            return '';
        }

        $fields = [];

        foreach ($schema as $field) {
            if (($field['key'] ?? '') === 'credential_id') {
                continue;
            }

            $key = $field['key'] ?? '';
            $type = $field['type'] ?? 'string';
            $required = ! empty($field['required']) ? ', required' : '';
            $label = $field['label'] ?? '';

            $parts = ["{$type}{$required}"];

            if (! empty($field['options']) && is_array($field['options'])) {
                $optionStrings = self::stringifySelectOptions(array_slice($field['options'], 0, 10));
                if ($optionStrings !== []) {
                    $parts[] = 'options: '.implode('|', $optionStrings);
                }
            }

            if (! empty($field['supports_expression'])) {
                $parts[] = 'expr';
            }

            if ($label && $label !== $key) {
                $parts[] = $label;
            }

            $fields[] = "`{$key}` (".implode(', ', $parts).')';
        }

        return implode(', ', $fields);
    }

    /**
     * @param  list<mixed>  $options
     * @return list<string>
     */
    protected static function stringifySelectOptions(array $options): array
    {
        $strings = [];

        foreach ($options as $opt) {
            if (is_string($opt) || is_int($opt) || is_float($opt)) {
                $strings[] = (string) $opt;

                continue;
            }

            if (is_array($opt)) {
                $value = $opt['value'] ?? null;
                $label = $opt['label'] ?? null;
                if ($value !== null && $value !== '') {
                    $strings[] = (string) $value;
                } elseif ($label !== null && $label !== '') {
                    $strings[] = (string) $label;
                } else {
                    $strings[] = json_encode($opt, JSON_UNESCAPED_UNICODE) ?: '';
                }

                continue;
            }

            $strings[] = (string) $opt;
        }

        return array_values(array_filter($strings, fn (string $s): bool => $s !== ''));
    }

    protected static function formatOutputSchema(array $schema): string
    {
        if (empty($schema)) {
            return '';
        }

        $parts = [];

        foreach ($schema as $port => $fields) {
            $fieldNames = array_map(fn ($f) => "`{$f['key']}`", $fields);
            $parts[] = "{$port}: ".implode(', ', $fieldNames);
        }

        return implode('; ', $parts);
    }
}
