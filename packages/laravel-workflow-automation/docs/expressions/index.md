<div v-pre>

# Expression Engine

Any config value in a node can contain `{{ expression }}` blocks. These are evaluated at runtime using a custom recursive descent parser — no `eval()` is ever used.

## Syntax

Expressions are wrapped in double curly braces:

```text
{{ item.name }}
{{ upper(item.email) }}
{{ item.total > 500 ? "VIP" : "Standard" }}
```

**Type preservation:** If the entire config value is a single `{{ expr }}`, the raw value is returned (preserving type). If mixed with text, results are cast to strings.

```text
"{{ item.total }}"                → 500 (integer)
"Order total: {{ item.total }}"  → "Order total: 500" (string)
```

## Variables

| Variable | Description | Example |
| --- | --- | --- |
| `item` | Current item being processed | `{{ item }}` |
| `item.field` | Field on the current item | `{{ item.email }}` |
| `item.nested.field` | Nested field (dot notation) | `{{ item.address.city }}` |
| `item.array.0` | Array element by index | `{{ item.tags.0 }}` |
| `item._loop_item` | Current loop element | `{{ item._loop_item.name }}` |
| `item._loop_index` | Current loop index | `{{ item._loop_index }}` |
| `item._loop_parent` | Parent item in loop | `{{ item._loop_parent.order_id }}` |
| `trigger` | Trigger node output | `{{ trigger.0.name }}` |
| `node.{id}.main` | Output from a specific node | `{{ node.5.main.0.email }}` |
| `nodes` | All node outputs | `{{ nodes }}` |
| `payload` | Initial workflow payload | `{{ payload }}` |

## Variable Discovery

When editing a node's config in the visual editor, the system helps you discover which variables are available:

### Autocomplete

Type `{{` in any expression-enabled field to open an autocomplete dropdown. It shows:

- **Global variables** — `item`, `payload`, `trigger`
- **Upstream node outputs** — variables from nodes connected before the current one
- **Built-in functions** — all 38 functions listed below

Use arrow keys to navigate, Enter/Tab to select. The expression is auto-completed with closing `}}`.

### Variable Panel

Below the config form, an **Available Variables** panel lists all variables grouped by:

- **Globals** — always available (`item`, `payload`, `trigger`)
- **Upstream Nodes** — each upstream node's output fields (e.g. `nodes.HTTP Request.main.0.http_response.status`)
- **Functions** — all expression functions with their signatures

Click any variable to copy `{{ path }}` to your clipboard.

### Output Schema

Each node declares what variables it produces via `outputSchema()`. This powers both autocomplete and the variable panel. See [Custom Nodes](/advanced/custom-nodes) for how to define output schemas in your own nodes.

## Operators

### Arithmetic

| Operator | Description | Example |
| --- | --- | --- |
| `+` | Addition / string concatenation | `{{ item.a + item.b }}` |
| `-` | Subtraction | `{{ item.total - item.discount }}` |
| `*` | Multiplication | `{{ item.qty * item.price }}` |
| `/` | Division | `{{ item.total / item.count }}` |
| `%` | Modulo | `{{ item.id % 2 }}` |

### Comparison

| Operator | Description |
| --- | --- |
| `==` | Equals (loose) |
| `!=` | Not equals |
| `>` | Greater than |
| `<` | Less than |
| `>=` | Greater or equal |
| `<=` | Less or equal |

### Logical

| Operator | Description |
| --- | --- |
| `&&` | Logical AND |
| `\|\|` | Logical OR |
| `!` | Logical NOT (unary) |

### Ternary

```text
{{ item.total > 1000 ? "VIP" : "Standard" }}
{{ item.name ? item.name : "Anonymous" }}
```

## Operator Precedence

From lowest to highest:

1. Ternary (`? :`)
2. Logical OR (`||`)
3. Logical AND (`&&`)
4. Equality (`==`, `!=`)
5. Comparison (`>`, `<`, `>=`, `<=`)
6. Addition / Subtraction (`+`, `-`)
7. Multiplication / Division / Modulo (`*`, `/`, `%`)
8. Unary (`!`, `-`)
9. Primary (literals, variables, functions, parentheses)

Use parentheses to override: `{{ (a + b) * c }}`

## Literals

| Type | Example |
| --- | --- |
| Integer | `42`, `-5` |
| Float | `3.14`, `-0.5` |
| String | `"hello"`, `'world'` |
| Boolean | `true`, `false` |
| Null | `null` |
| Array | `[1, 2, 3]`, `["a", "b"]` |

## Built-in Functions (38)

### String Functions

| Function | Signature | Example |
| --- | --- | --- |
| `upper` | `upper(str)` | `{{ upper("hello") }}` → `"HELLO"` |
| `lower` | `lower(str)` | `{{ lower("HELLO") }}` → `"hello"` |
| `trim` | `trim(str)` | `{{ trim("  hi  ") }}` → `"hi"` |
| `length` | `length(str\|arr)` | `{{ length("hello") }}` → `5` |
| `substr` | `substr(str, start, len?)` | `{{ substr("hello", 0, 3) }}` → `"hel"` |
| `replace` | `replace(search, replace, subject)` | `{{ replace("@", "[at]", item.email) }}` |
| `contains` | `contains(haystack, needle)` | `{{ contains(item.tags, "vip") }}` |
| `starts_with` | `starts_with(str, prefix)` | `{{ starts_with(item.url, "https") }}` |
| `ends_with` | `ends_with(str, suffix)` | `{{ ends_with(item.email, ".com") }}` |
| `split` | `split(separator, string)` | `{{ split(",", "a,b,c") }}` → `["a","b","c"]` |
| `join` | `join(glue, array)` | `{{ join(", ", item.tags) }}` → `"a, b, c"` |

### Number Functions

| Function | Signature | Example |
| --- | --- | --- |
| `round` | `round(val, precision?)` | `{{ round(3.14159, 2) }}` → `3.14` |
| `ceil` | `ceil(val)` | `{{ ceil(3.2) }}` → `4` |
| `floor` | `floor(val)` | `{{ floor(3.8) }}` → `3` |
| `abs` | `abs(val)` | `{{ abs(-5) }}` → `5` |
| `min` | `min(a, b, ...)` | `{{ min(10, 5, 20) }}` → `5` |
| `max` | `max(a, b, ...)` | `{{ max(10, 5, 20) }}` → `20` |
| `sum` | `sum(array)` | `{{ sum([10, 20, 30]) }}` → `60` |
| `avg` | `avg(array)` | `{{ avg([10, 20, 30]) }}` → `20` |

### Array Functions

| Function | Signature | Example |
| --- | --- | --- |
| `count` | `count(arr)` | `{{ count(item.items) }}` → `3` |
| `first` | `first(arr)` | `{{ first(item.tags) }}` → `"php"` |
| `last` | `last(arr)` | `{{ last(item.tags) }}` → `"go"` |
| `pluck` | `pluck(arr, key)` | `{{ pluck(item.users, "name") }}` → `["Alice", "Bob"]` |
| `flatten` | `flatten(arr)` | `{{ flatten([[1,2],[3]]) }}` → `[1,2,3]` |
| `unique` | `unique(arr)` | `{{ unique([1,2,2,3]) }}` → `[1,2,3]` |
| `sort` | `sort(arr)` | `{{ sort([3,1,2]) }}` → `[1,2,3]` |

### Date Functions

| Function | Signature | Example |
| --- | --- | --- |
| `now` | `now()` | `{{ now() }}` → `"2024-01-15T08:00:00Z"` |
| `date_format` | `date_format(date, format)` | `{{ date_format(now(), "Y-m-d") }}` → `"2024-01-15"` |
| `date_diff` | `date_diff(date1, date2, unit?)` | `{{ date_diff(item.created_at, now(), "days") }}` → `30` |

Units for `date_diff`: `seconds`, `minutes`, `hours`, `days` (default).

### Type Functions

| Function | Signature | Example |
| --- | --- | --- |
| `int` | `int(val)` | `{{ int("42") }}` → `42` |
| `float` | `float(val)` | `{{ float("3.14") }}` → `3.14` |
| `string` | `string(val)` | `{{ string(42) }}` → `"42"` |
| `bool` | `bool(val)` | `{{ bool(1) }}` → `true` |
| `json_encode` | `json_encode(val)` | `{{ json_encode(item) }}` → `"{...}"` |
| `json_decode` | `json_decode(str)` | `{{ json_decode(item.raw) }}` → array |

## Practical Examples

### Field access

```text
{{ item.email }}                    → "alice@example.com"
{{ item.address.city }}             → "Istanbul"
{{ item.tags.0 }}                   → "premium"
```

### Computed values

```text
{{ item.quantity * item.unit_price }}           → 150
{{ round(item.total * 0.18, 2) }}              → 27.00
{{ item.total > 500 ? "VIP" : "Standard" }}    → "VIP"
```

### String operations

```text
{{ upper(item.status) }}                       → "ACTIVE"
{{ "Order #" + string(item.id) }}              → "Order #42"
{{ contains(lower(item.text), "urgent") }}     → true
```

### Array operations

```text
{{ count(item.line_items) }}                   → 3
{{ sum(pluck(item.line_items, "amount")) }}    → 450
{{ join(", ", pluck(item.tags, "name")) }}     → "php, laravel"
```

### Date operations

```text
{{ date_format(now(), "M d, Y") }}             → "Jan 15, 2024"
{{ date_diff(item.created_at, now(), "days") }} → 30
```

## Expression Modes

Configured in `config/workflow-automation.php`:

```php
'expression_mode' => 'safe', // default
```

| Mode | Functions | Use Case |
| --- | --- | --- |
| `safe` | All 38 built-in functions | Recommended for most apps |
| `strict` | No functions, only dot-notation | Maximum security lockdown |

## Custom Functions

Register custom functions in a service provider:

```php
use Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface;

public function boot(): void
{
    $evaluator = app(ExpressionEvaluatorInterface::class);

    $evaluator->registerFunction('currency', function (float $amount, string $code = 'USD'): string {
        return number_format($amount, 2) . ' ' . $code;
    });

    $evaluator->registerFunction('slug', function (string $value): string {
        return \Illuminate\Support\Str::slug($value);
    });
}
```

Use in expressions:

```text
{{ currency(item.total, "EUR") }}  → "1,250.00 EUR"
{{ slug(item.title) }}             → "my-blog-post"
```

</div>
