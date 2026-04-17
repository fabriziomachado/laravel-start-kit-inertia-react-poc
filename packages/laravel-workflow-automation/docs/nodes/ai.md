<div v-pre>

# AI

Send prompts to any AI provider from within a workflow using [Laravel AI](https://laravel.com/ai). Supports all providers (OpenAI, Anthropic, Gemini, Groq, Mistral, DeepSeek, Ollama, xAI, Cohere), configurable model/temperature/max tokens, and expression-based dynamic prompts.

**Node key:** `ai` · **Type:** Action

::: tip Prerequisite
This node requires the `laravel/ai` package. Install it with:
```bash
composer require laravel/ai
```
Configure your provider API keys in `.env` as described in the [Laravel AI documentation](https://laravel.com/ai).
:::

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `prompt` | textarea | Yes | Yes | The prompt to send to the AI model |
| `system_prompt` | textarea | No | Yes | System instructions for the AI model |
| `provider` | string | No | No | Provider name: `openai`, `anthropic`, `gemini`, `groq`, `mistral`, `deepseek`, `ollama`, `xai`, `cohere` |
| `model` | string | No | No | Model identifier (e.g. `gpt-4o`, `claude-sonnet-4-5-20250514`) |
| `temperature` | string | No | No | Sampling temperature (0–2). Lower = more deterministic |
| `max_tokens` | integer | No | No | Maximum tokens in the response |
| `output_key` | string | No | No | Key name for the AI response in the output item (default: `ai_response`) |

When `provider` or `model` is not set per-node, the defaults from `config/workflow-automation.php` are used.

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process — each item triggers one AI call |
| Output | `main` | Items with AI response added |
| Output | `error` | Items where the AI call threw an exception |

## Default Config

Set global defaults in `config/workflow-automation.php`:

```php
'ai' => [
    'default_provider' => env('WORKFLOW_AI_PROVIDER'),
    'default_model'    => env('WORKFLOW_AI_MODEL'),
    'max_tokens'       => env('WORKFLOW_AI_MAX_TOKENS', 4096),
],
```

```ini
WORKFLOW_AI_PROVIDER=openai
WORKFLOW_AI_MODEL=gpt-4o
WORKFLOW_AI_MAX_TOKENS=4096
```

## Example: Content Summarization

```php
$trigger = $workflow->addNode('Start', 'manual');

$summarize = $workflow->addNode('Summarize', 'ai', [
    'prompt'        => 'Summarize this article in 2 sentences:\n\n{{ item.content }}',
    'system_prompt' => 'You are a concise technical writer.',
    'provider'      => 'anthropic',
    'model'         => 'claude-sonnet-4-5-20250514',
    'max_tokens'    => 200,
    'output_key'    => 'summary',
]);

$trigger->connect($summarize);
$workflow->activate();

$run = $workflow->start([
    ['title' => 'Laravel 12', 'content' => 'Laravel 12 introduces...'],
]);
// Output: item.summary = "Laravel 12 introduces..."
```

## Example: Sentiment Analysis Pipeline

```php
$trigger = $workflow->addNode('Start', 'manual');

$analyze = $workflow->addNode('Analyze Sentiment', 'ai', [
    'prompt'        => 'Classify the sentiment of this review as positive, negative, or neutral. Reply with one word only.\n\nReview: {{ item.review }}',
    'system_prompt' => 'You are a sentiment classifier. Reply with exactly one word: positive, negative, or neutral.',
    'temperature'   => '0',
    'output_key'    => 'sentiment',
]);

$route = $workflow->addNode('Route by Sentiment', 'switch', [
    'field' => '{{ item.sentiment }}',
    'cases' => [
        ['value' => 'negative', 'port' => 'negative'],
        ['value' => 'positive', 'port' => 'positive'],
    ],
    'default_port' => 'neutral',
]);

$alert = $workflow->addNode('Alert Team', 'send_mail', [
    'to'      => 'support@company.com',
    'subject' => 'Negative review from {{ item.customer }}',
    'body'    => '{{ item.review }}',
]);

$trigger->connect($analyze);
$analyze->connect($route);
$route->connect($alert, 'negative');

$workflow->activate();
```

## Example: Bulk Email Personalization

```php
$trigger = $workflow->addNode('Start', 'manual');

$personalize = $workflow->addNode('Generate Email', 'ai', [
    'prompt'        => 'Write a short personalized welcome email for {{ item.name }} who signed up as a {{ item.plan }} user. Include their name and mention one benefit of their plan.',
    'system_prompt' => 'You write friendly, professional welcome emails. Keep them under 100 words.',
    'provider'      => 'openai',
    'model'         => 'gpt-4o-mini',
    'max_tokens'    => 300,
    'output_key'    => 'email_body',
]);

$send = $workflow->addNode('Send Welcome', 'send_mail', [
    'to'      => '{{ item.email }}',
    'subject' => 'Welcome to our platform, {{ item.name }}!',
    'body'    => '{{ item.email_body }}',
]);

$trigger->connect($personalize);
$personalize->connect($send);

$workflow->activate();

$run = $workflow->start([
    ['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro'],
    ['name' => 'Bob',   'email' => 'bob@example.com',   'plan' => 'starter'],
]);
```

## Example: Content Moderation with Error Handling

```php
$trigger  = $workflow->addNode('Start', 'manual');

$moderate = $workflow->addNode('Moderate Content', 'ai', [
    'prompt'        => 'Review this user comment for harmful content. Reply with JSON: {"safe": true/false, "reason": "explanation"}\n\nComment: {{ item.comment }}',
    'system_prompt' => 'You are a content moderator. Always reply with valid JSON.',
    'temperature'   => '0',
    'output_key'    => 'moderation',
]);

$errorHandler = $workflow->addNode('Handle AI Error', 'error_handler', [
    'rules'         => [
        ['match' => 'rate limit', 'route' => 'retry'],
    ],
    'default_route' => 'log',
]);

$trigger->connect($moderate);
$moderate->connect($errorHandler, 'error');
```

## Input / Output Example

**Input:**

```php
[
    ['review' => 'This product is amazing!', 'customer' => 'Alice'],
]
```

**Output (on `main`):**

```php
[
    [
        'review'      => 'This product is amazing!',
        'customer'    => 'Alice',
        'ai_response' => 'positive',
        'ai_usage'    => [
            'input_tokens'  => 28,
            'output_tokens' => 1,
        ],
    ],
]
```

With `output_key: 'sentiment'`:

```php
[
    [
        'review'   => 'This product is amazing!',
        'customer' => 'Alice',
        'sentiment' => 'positive',
        'ai_usage'  => [
            'input_tokens'  => 28,
            'output_tokens' => 1,
        ],
    ],
]
```

## Tips

- Use `temperature: 0` for classification, extraction, and structured output tasks — it makes responses deterministic
- Use `output_key` to give the AI response a meaningful name (e.g. `summary`, `sentiment`, `translation`) instead of the generic `ai_response`
- The `ai_usage` field is always included — use it to track token consumption across workflow runs
- Each item in the input triggers a separate AI call — use the Loop node to batch-process large datasets
- Combine with IF/Switch nodes to branch workflows based on AI output
- Set `system_prompt` to constrain the AI's behavior — e.g. "Reply with JSON only" or "Reply with one word"
- Use expressions in prompts to inject item data: `{{ item.content }}`, `{{ item.name }}`
- The node does NOT stream responses — it waits for the full response before continuing
- If the `laravel/ai` package is not installed, the node throws a clear `RuntimeException` with installation instructions
- Set default provider/model in config to avoid repeating them on every node

</div>
