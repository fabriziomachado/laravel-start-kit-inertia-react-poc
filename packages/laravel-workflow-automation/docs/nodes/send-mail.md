# Send Mail

Sends an email for each item passing through it using Laravel's Mail facade.

**Node key:** `send_mail` · **Type:** Action

## Send Modes

The node supports two modes via the `send_mode` config:

- **inline** (default) — Compose the email directly in the node config (to, subject, body, etc.)
- **mailable** — Use a Laravel Mailable class for full template control

## Config

### Mode Selector

| Key | Type | Required | Description |
| --- | --- | --- | --- |
| `send_mode` | select | Yes | `inline` or `mailable` |

### Inline Mode

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `to` | string | Yes | Yes | Recipient(s), comma-separated |
| `cc` | string | No | Yes | CC recipient(s), comma-separated |
| `bcc` | string | No | Yes | BCC recipient(s), comma-separated |
| `reply_to` | string | No | Yes | Reply-To address |
| `subject` | string | Yes | Yes | Email subject line |
| `body` | textarea | Yes | Yes | Email body content |
| `from` | string | No | No | Override default from address |
| `is_html` | boolean | No | No | Send as HTML instead of plain text |
| `attachments` | keyvalue | No | Yes | File attachments (display name → file path) |

### Mailable Mode

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `mailable_class` | string | Yes | No | Fully qualified Mailable class name |
| `mailable_to` | string | Yes | Yes | Recipient(s), comma-separated |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items that were emailed successfully (with `mail_sent: true`) |
| Output | `error` | Items that failed to send (with error message) |

## Behavior

The node iterates over every input item and sends one email per item:

### Inline Mode

1. Resolves `to`, `subject`, `body`, and other fields as expressions against the current item
2. Parses comma-separated email addresses for `to`, `cc`, and `bcc`
3. Sends via `Mail::raw()` (plain text) or `Mail::html()` (when `is_html` is `true`)
4. Applies `from`, `cc`, `bcc`, `reply_to`, and `attachments` if provided
5. On success: item goes to `main` port with `mail_sent: true` added
6. On failure: item goes to `error` port with the exception message

### Mailable Mode

1. Instantiates the Mailable class, passing the current `$item` array to the constructor
2. Sends to the resolved `mailable_to` addresses via `Mail::to()->send()`
3. Subject, body, template, CC, BCC, attachments are all controlled by the Mailable class

## Examples

### Inline — Simple Text Email

```php
$email = $workflow->addNode('Confirmation Email', 'send_mail', [
    'send_mode' => 'inline',
    'to'        => '{{ item.customer_email }}',
    'subject'   => 'Order #{{ item.id }} confirmed',
    'body'      => 'Hi {{ item.customer_name }}, your order for ${{ item.total }} has been confirmed.',
]);
```

### Inline — HTML with CC and Attachments

```php
$email = $workflow->addNode('Invoice Email', 'send_mail', [
    'send_mode'   => 'inline',
    'to'          => '{{ item.customer_email }}',
    'cc'          => 'billing@example.com',
    'subject'     => 'Invoice #{{ item.invoice_number }}',
    'body'        => '<h1>Invoice</h1><p>Amount: ${{ item.total }}</p>',
    'is_html'     => true,
    'attachments' => ['invoice.pdf' => '{{ item.invoice_path }}'],
]);
```

### Mailable — Custom Template

```php
$email = $workflow->addNode('Welcome Email', 'send_mail', [
    'send_mode'      => 'mailable',
    'mailable_class' => 'App\\Mail\\WelcomeEmail',
    'mailable_to'    => '{{ item.email }}',
]);
```

Your Mailable class receives the workflow item:

```php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(public array $item) {}

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: ['name' => $this->item['name']],
        );
    }
}
```

## Input / Output Example

**Input (on `main`):**

```php
[
    ['customer_email' => 'alice@example.com', 'customer_name' => 'Alice', 'id' => 42, 'total' => 99.90],
]
```

**Output (on `main` — success):**

```php
[
    ['customer_email' => 'alice@example.com', 'customer_name' => 'Alice', 'id' => 42, 'total' => 99.90, 'mail_sent' => true],
]
```

**Output (on `error` — failure):**

```php
[
    ['customer_email' => 'invalid', 'customer_name' => 'Alice', 'id' => 42, 'total' => 99.90, 'error' => 'Expected valid email address'],
]
```

## Tips

- Existing workflows without `send_mode` default to `inline` — fully backward compatible
- Multiple recipients: use comma-separated emails in `to`, `cc`, or `bcc` (e.g. `alice@example.com, bob@example.com`)
- For complex email templates, use **mailable mode** — it gives you full control over views, markdown, attachments, headers, etc.
- If a send fails (SMTP timeout, invalid recipient), the item is routed to `error` so you can handle failures downstream
- Connect the `error` port to an [Error Handler](/nodes/error-handler) for centralized error routing
