<div v-pre>

# HTTP Request

Makes an HTTP call for each item using Laravel's Http facade.

**Node key:** `http_request` · **Type:** Action

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `credential_id` | credential | No | No | Authentication credential (see [Credential Vault](/advanced/credentials)) |
| `url` | string | Yes | Yes | The URL to call |
| `method` | select | Yes | No | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| `headers` | keyvalue | No | Yes | Custom request headers |
| `body` | json | No | Yes | Request body as JSON |
| `timeout` | integer | No | No | Timeout in seconds (default: 30) |
| `include_response` | boolean | No | No | Include the full response in the output item |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items with optional response data |
| Output | `error` | Items whose request failed |

## Behavior

For each input item:

1. If `credential_id` is set, resolves the credential and adds auth headers automatically
2. Resolves `url`, `headers`, and `body` as expressions against the current item
3. Sends the HTTP request using the configured `method`
4. If `timeout` is set, overrides the default 30-second limit
5. When `include_response` is `true`, adds `http_response` to the item containing `status`, `body`, and `headers`
6. On success: item goes to `main` port
7. On failure (connection error, timeout): item goes to `error` port

## Example

Using a credential for authentication (recommended):

```php
$apiCall = $workflow->addNode('Check Inventory', 'http_request', [
    'credential_id'    => 1, // references a stored Bearer Token credential
    'url'              => 'https://inventory.example.com/api/products/{{ item.product_id }}',
    'method'           => 'GET',
    'timeout'          => 15,
    'include_response' => true,
]);
```

Without credentials (legacy — secrets stored in plain text):

```php
$apiCall = $workflow->addNode('Check Inventory', 'http_request', [
    'url'              => 'https://inventory.example.com/api/products/{{ item.product_id }}',
    'method'           => 'GET',
    'headers'          => ['Authorization' => 'Bearer my-api-token'],
    'timeout'          => 15,
    'include_response' => true,
]);
```

## Input / Output Example

**Input:**

```php
[
    ['product_id' => 'SKU-001', 'name' => 'Widget'],
]
```

**Output (with `include_response: true`):**

```php
[
    [
        'product_id'    => 'SKU-001',
        'name'          => 'Widget',
        'http_response' => [
            'status'  => 200,
            'body'    => ['stock' => 42, 'warehouse' => 'NYC'],
            'headers' => ['content-type' => 'application/json'],
        ],
    ],
]
```

Access response data downstream: `{{ item.http_response.body.stock }}`

## Tips

- **Use credentials** instead of hardcoding tokens in headers — secrets are encrypted at rest and never exposed in API responses or run logs
- Set `timeout` to a lower value for time-sensitive workflows to fail fast
- Connection errors, timeouts, and non-2xx responses all route to `error`
- The `body` config is sent as JSON for POST/PUT/PATCH requests


</div>
