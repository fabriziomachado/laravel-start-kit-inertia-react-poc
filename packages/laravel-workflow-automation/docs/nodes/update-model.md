<div v-pre>

# Update Model

Finds an Eloquent model by a field and updates it with new values.

**Node key:** `update_model` · **Type:** Action

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `model` | string | Yes | No | Fully qualified model class (e.g. `App\\Models\\Order`) |
| `find_by` | string | Yes | No | Field to look up the model by (e.g. `id`, `email`) |
| `find_value` | string | Yes | Yes | Value to search for, evaluated per item |
| `fields` | keyvalue | Yes | Yes | Key-value pairs of fields to update |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items with `updated_model` data attached |
| Output | `error` | Items that failed (model not found, etc.) |

## Behavior

For each input item:

1. Resolves `find_value` expression to get the lookup value
2. Calls `Model::where($find_by, $find_value)->firstOrFail()`
3. Evaluates each `fields` entry as an expression
4. Calls `->update(...)` with the resolved values
5. Adds refreshed model data as `updated_model` to the output item

## Example

```php
$update = $workflow->addNode('Update Order Status', 'update_model', [
    'model'      => 'App\\Models\\Order',
    'find_by'    => 'id',
    'find_value' => '{{ item.order_id }}',
    'fields'     => [
        'status'     => 'shipped',
        'shipped_at' => '{{ now() }}',
    ],
]);
```

## Input / Output Example

**Input:**

```php
[
    ['order_id' => 42, 'tracking_number' => 'TRK-123'],
]
```

**Output (on `main`):**

```php
[
    [
        'order_id'        => 42,
        'tracking_number' => 'TRK-123',
        'updated_model'   => [
            'id'         => 42,
            'status'     => 'shipped',
            'shipped_at' => '2024-01-15 08:00:00',
            // ... all model fields
        ],
    ],
]
```

Access updated data downstream: `{{ item.updated_model.status }}`

## Tips

- Uses `firstOrFail()` — if no record is found, the item routes to `error` with `ModelNotFoundException`
- All `fields` values support expressions: mix static (`'shipped'`) with dynamic (`'{{ item.tracking_number }}'`)
- The refreshed model data is available at `item.updated_model` for downstream nodes


</div>
