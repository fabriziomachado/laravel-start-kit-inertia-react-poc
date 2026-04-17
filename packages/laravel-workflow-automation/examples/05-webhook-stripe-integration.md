# Stripe Webhook Handler

> English | **[Türkçe](tr/05-webhook-stripe-entegrasyonu.md)**

Receive Stripe webhook events, route them by event type, update the order in your database, and send the right email. This example shows the `webhook` trigger, `switch` routing, `update_model`, and `delay` nodes.

## Flow

```
[Webhook Trigger] → [Switch: event type]
                        ├─ payment_succeeded → [Update Model: paid]    → [Send Mail: receipt]
                        ├─ payment_failed    → [Update Model: failed]  → [Send Mail: retry notice] → [Delay: 1h] → [HTTP: retry charge]
                        └─ refund            → [Update Model: refunded] → [Send Mail: refund confirmation]
```

## Step 1 — Define the Workflow

Create an artisan command and run it once with `php artisan workflow:setup-stripe`.

```php
// app/Console/Commands/SetupStripeWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupStripeWorkflow extends Command
{
    protected $signature = 'workflow:setup-stripe';
    protected $description = 'Create the Stripe webhook handler workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Stripe Webhooks']);

        $trigger = $workflow->addNode('Stripe Webhook', 'webhook', [
            'method'    => 'POST',
            'auth_type' => 'header_key',
        ]);

        $switchEvent = $workflow->addNode('Route by Event', 'switch', [
            'field' => 'type',
            'cases' => [
                ['port' => 'case_succeeded', 'operator' => 'equals', 'value' => 'payment_intent.succeeded'],
                ['port' => 'case_failed',    'operator' => 'equals', 'value' => 'payment_intent.payment_failed'],
                ['port' => 'case_refund',    'operator' => 'equals', 'value' => 'charge.refunded'],
            ],
        ]);

        // ── Payment succeeded ─────────────────────────────────

        $markPaid = $workflow->addNode('Mark Paid', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_payment_intent',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => ['status' => 'paid', 'paid_at' => '{{ now() }}'],
        ]);

        $sendReceipt = $workflow->addNode('Send Receipt', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Payment Confirmed — Order #{{ item.data.object.metadata.order_id }}',
            'body'    => 'Your payment of ${{ item.data.object.amount / 100 }} has been confirmed.',
        ]);

        // ── Payment failed ────────────────────────────────────

        $markFailed = $workflow->addNode('Mark Failed', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_payment_intent',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => ['status' => 'payment_failed'],
        ]);

        $sendRetryNotice = $workflow->addNode('Retry Notice', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Payment Failed — Action Required',
            'body'    => 'Your payment could not be processed. We will retry in 1 hour.',
        ]);

        $delay = $workflow->addNode('Wait 1 Hour', 'delay', [
            'delay_seconds' => 3600, // 1 hour
        ]);

        $retryCharge = $workflow->addNode('Retry Charge', 'http_request', [
            'url'    => 'https://api.stripe.com/v1/payment_intents/{{ item.data.object.id }}/confirm',
            'method' => 'POST',
        ]);

        // ── Refund ────────────────────────────────────────────

        $markRefunded = $workflow->addNode('Mark Refunded', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_charge_id',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => ['status' => 'refunded', 'refunded_at' => '{{ now() }}'],
        ]);

        $sendRefundEmail = $workflow->addNode('Refund Confirmation', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Refund Processed',
            'body'    => 'Your refund of ${{ item.data.object.amount_refunded / 100 }} has been processed.',
        ]);

        // Edges
        $trigger->connect($switchEvent);

        $switchEvent->connect($markPaid, sourcePort: 'case_succeeded');
        $markPaid->connect($sendReceipt);

        $switchEvent->connect($markFailed, sourcePort: 'case_failed');
        $markFailed->connect($sendRetryNotice);
        $sendRetryNotice->connect($delay);
        $delay->connect($retryCharge);

        $switchEvent->connect($markRefunded, sourcePort: 'case_refund');
        $markRefunded->connect($sendRefundEmail);

        $workflow->activate();

        $this->info("Stripe Webhooks workflow created (ID: {$workflow->id})");
    }
}
```

## Step 2 — Get the Webhook URL

After running the command, the `webhook` node generates a unique UUID path:

```php
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;

$node = WorkflowNode::where('name', 'Stripe Webhook')->first();
$url = url("workflow-webhook/{$node->config['path']}");
// → https://yourapp.com/workflow-webhook/a1b2c3d4-e5f6-...
```

Point Stripe's webhook settings to this URL. No code needed in your app — the package handles the incoming request, validates auth, and runs the workflow.

## What Happens

**`payment_intent.succeeded`:**

1. **Switch** → matches `case_succeeded`
2. **Update Model** → Finds `Order` by `stripe_payment_intent`, sets `status: paid`
3. **Send Mail** → Customer gets receipt

**`payment_intent.payment_failed`:**

1. **Switch** → matches `case_failed`
2. **Update Model** → Sets `status: payment_failed`
3. **Send Mail** → Customer gets retry notice
4. **Delay** → Workflow pauses for 1 hour (queue-based, non-blocking)
5. **HTTP Request** → Retries the charge via Stripe API

**`charge.refunded`:**

1. **Switch** → matches `case_refund`
2. **Update Model** → Sets `status: refunded`
3. **Send Mail** → Customer gets refund confirmation

## Concepts Demonstrated

| Concept | How |
|---------|-----|
| Webhook trigger | External service (Stripe) sends POST to a generated URL |
| Multi-way routing | `switch` routes by event type to different branches |
| Database updates | `update_model` finds and updates Eloquent models |
| Non-blocking delay | `delay` uses Laravel queues — the worker is free during the wait |
| Expression nesting | `{{ item.data.object.metadata.order_id }}` accesses deeply nested data |
