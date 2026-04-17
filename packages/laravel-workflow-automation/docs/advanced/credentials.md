<div v-pre>

# Credential Vault

The credential vault provides encrypted storage for sensitive values like API keys, tokens, and passwords. Instead of storing secrets in plain text inside node configs, nodes reference credentials by ID — the actual secret is encrypted at rest and only decrypted in-memory during execution.

## How It Works

```
workflow_credentials table
│  data column: AES-256-CBC encrypted
│
├─ Node config stores: { "credential_id": 5 }
│
└─ At runtime: CredentialResolutionMiddleware
   decrypts → injects into $config['_credential']
   → node reads auth data → never persisted to logs
```

**Key guarantees:**

- Secrets are encrypted at rest using Laravel's `Crypt` (your `APP_KEY`)
- The REST API **never** returns decrypted credential data
- Run logs **never** contain credential values
- Decrypted data only exists in-memory during node execution

## Built-in Credential Types

| Type | Key | Fields |
| --- | --- | --- |
| Bearer Token | `bearer_token` | `token` |
| Basic Auth | `basic_auth` | `username`, `password` |
| Header Auth | `header_auth` | `header_name`, `header_value` |
| API Key | `api_key` | `api_key` |

## Using Credentials in PHP

### Create a Credential

```php
use Aftandilmmd\WorkflowAutomation\Models\WorkflowCredential;

$credential = WorkflowCredential::create([
    'name' => 'Stripe API Key',
    'type' => 'bearer_token',
    'data' => ['token' => 'sk_live_xxx'], // automatically encrypted
]);
```

### Reference in Node Config

```php
$httpNode = $workflow->addNode('Call API', 'http_request', [
    'credential_id' => $credential->id,
    'url'           => 'https://api.stripe.com/v1/charges',
    'method'        => 'POST',
    'body'          => ['amount' => 2000, 'currency' => 'usd'],
]);
```

### Access Decrypted Data

```php
$credential = WorkflowCredential::find(1);
$data = $credential->getDecryptedData();
// ['token' => 'sk_live_xxx']
```

## Using Credentials via REST API

### Create

```http
POST /workflow-engine/credentials
Content-Type: application/json

{
  "name": "GitHub Token",
  "type": "bearer_token",
  "data": {
    "token": "ghp_xxx"
  }
}
```

### List (data never returned)

```http
GET /workflow-engine/credentials
```

```json
{
  "data": [
    {"id": 1, "name": "GitHub Token", "type": "bearer_token", "meta": null}
  ]
}
```

### Get Available Types

```http
GET /workflow-engine/credentials-types
```

Returns schema for each credential type so the UI can render the correct form fields.

## Using Credentials in the Editor

When a node supports credentials (e.g. HTTP Request), the config panel shows a **credential dropdown** with a **"+ New"** button. Selecting "+ New" opens a modal where you can:

1. Name the credential
2. Select the type (Bearer Token, Basic Auth, etc.)
3. Fill in the secret fields
4. Save — the secret is encrypted and the credential ID is stored in the node config

## How Credential Resolution Works

The `CredentialResolutionMiddleware` is registered in the node execution pipeline. When a node executes:

1. Middleware checks if `$config['credential_id']` exists
2. If yes, loads the `WorkflowCredential` model and decrypts the data
3. Injects the decrypted data as `$config['_credential']`
4. The node reads `$config['_credential']` to build auth headers (or use the data however it needs)
5. After execution, `_credential` is not persisted anywhere

```php
// Inside CredentialResolutionMiddleware
if (isset($config['credential_id'])) {
    $credential = WorkflowCredential::findOrFail($config['credential_id']);
    $config['_credential'] = $credential->getDecryptedData();
}
return $next($node, $input, $config);
```

## Nodes That Support Credentials

| Node | Credential Types | Behavior |
| --- | --- | --- |
| HTTP Request | `bearer_token`, `basic_auth`, `header_auth`, `api_key` | Adds auth headers automatically |
| Webhook Trigger | `bearer_token`, `basic_auth`, `header_auth` | Validates incoming requests against credential |

## Creating Custom Credential Types

### 1. Implement the Interface

```php
<?php

namespace App\Credentials;

use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeInterface;

class OAuth2Credential implements CredentialTypeInterface
{
    public static function getKey(): string
    {
        return 'oauth2';
    }

    public static function getLabel(): string
    {
        return 'OAuth2';
    }

    public static function schema(): array
    {
        return [
            ['key' => 'client_id', 'type' => 'string', 'label' => 'Client ID', 'required' => true],
            ['key' => 'client_secret', 'type' => 'password', 'label' => 'Client Secret', 'required' => true],
            ['key' => 'token_url', 'type' => 'url', 'label' => 'Token URL', 'required' => true],
        ];
    }
}
```

### 2. Register in a Service Provider

```php
use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeRegistry;

public function boot(): void
{
    app(CredentialTypeRegistry::class)->register(OAuth2Credential::class);
}
```

### 3. Register via Plugin

Plugins can register credential types through `PluginContext`:

```php
public function register(PluginContext $context): void
{
    $context->registerCredentialType(OAuth2Credential::class);
}
```

## Security Considerations

- **APP_KEY rotation** — If you rotate your `APP_KEY`, all credential data becomes unreadable. Back up credentials before rotating.
- **Database access** — Anyone with direct database access can read the encrypted data column, but cannot decrypt without the `APP_KEY`.
- **API security** — The credential API endpoints use the same middleware as the rest of the package. Add `auth:sanctum` or similar to protect credential management.
- **Soft deletes** — Credentials are soft-deleted. Purge old credentials from the database if your security policy requires it.

</div>
