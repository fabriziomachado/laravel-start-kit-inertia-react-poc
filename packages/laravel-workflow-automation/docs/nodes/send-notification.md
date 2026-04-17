# Send Notification

Sends a Laravel notification to a notifiable model for each item.

**Node key:** `send_notification` · **Type:** Action

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `notification_class` | string | Yes | No | Fully qualified notification class |
| `notifiable_class` | string | Yes | No | Fully qualified notifiable model class |
| `notifiable_id` | string | Yes | Yes | Notifiable model ID (value or expression) |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items with `notification_sent: true` |
| Output | `error` | Items that failed to send |

## Behavior

For each input item:

1. Resolves `notifiable_id` to get the notifiable model's ID
2. Finds the model via `NotifiableClass::find($id)`
3. Instantiates the notification, passing the **full item array** to its constructor
4. Calls `$notifiable->notify($notification)`
5. Adds `notification_sent: true` to the output item

## Example

```php
$notify = $workflow->addNode('Notify Customer', 'send_notification', [
    'notification_class' => 'App\\Notifications\\OrderShipped',
    'notifiable_class'   => 'App\\Models\\User',
    'notifiable_id'      => '{{ item.user_id }}',
]);
```

Your notification class receives the full item:

```php
class OrderShipped extends Notification
{
    public function __construct(public array $data) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your order has shipped!')
            ->line('Order #' . $this->data['id'] . ' is on its way.');
    }
}
```

## Input / Output Example

**Input:**

```php
[
    ['id' => 42, 'user_id' => 1, 'status' => 'shipped'],
]
```

**Output (on `main`):**

```php
[
    ['id' => 42, 'user_id' => 1, 'status' => 'shipped', 'notification_sent' => true],
]
```

## Tips

- Works with any Laravel notification channel: mail, database, Slack, Vonage, or custom channels
- The notification constructor receives the full item array — access all upstream data inside `via()`, `toMail()`, etc.
- If the notifiable model is not found, the item routes to `error` with `ModelNotFoundException`
