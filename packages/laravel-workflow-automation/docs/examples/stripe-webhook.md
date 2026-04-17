<div v-pre>

# Stripe Webhook Handler

Handle Stripe webhook events with a single workflow. Incoming events are routed by type: successful payments update the order and send a receipt, failed payments send a failure notice and retry the charge, and refunds update the order status and send a confirmation.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - webhook         Receive Stripe event payloads     |
|  - switch          Route by event type (3 cases)     |
|  - update_model    Update order status (paid/refund) |
|  - send_mail (x3)  Receipt, failure, refund emails   |
|  - http_request    Retry failed Stripe charges       |
|                                                      |
|  Concepts: webhook with bearer auth, switch multi-   |
|  way routing, nested field access, expression        |
|  arithmetic (cents->dollars), env secrets,           |
|  fallthrough disabled                                |
+------------------------------------------------------+
```

## Workflow Diagram

```text
                          +------------------------+
                          |  Webhook: Stripe       |
                          |  (POST, bearer auth)   |
                          +----------+-------------+
                                     |
                                     v
                          +----------+-------------+
                          |  Switch: event type    |
                          +---+---------+------+---+
                              |         |      |
          case_payment_succeeded  case_payment_failed  case_refund
                              |         |      |
                              v         v      v
                  +-----------+-+  +----+----+ +--+---------+
                  | Update      |  | Email:  | | Update     |
                  | Model:      |  | failure | | Model:     |
                  | order=paid  |  | notice  | | order=     |
                  +------+------+  +----+----+ | refunded   |
                         |              |      +------+-----+
                         v              v            |
                  +------+------+  +----+--------+   v
                  | Email:      |  | HTTP: retry |  +------+--------+
                  | receipt     |  | charge      |  | Email: refund |
                  +-------------+  +-------------+  | confirmation  |
                                                    +---------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupStripeWebhook.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupStripeWebhook extends Command
{
    protected $signature = 'workflow:setup-stripe';
    protected $description = 'Create the Stripe webhook handler workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Stripe Webhook Handler']);

        // 1. Webhook trigger — Stripe POSTs here with bearer auth
        $trigger = $workflow->addNode('Stripe Webhook', 'webhook', [
            'method'     => 'POST',
            'auth_type'  => 'bearer',
            'auth_value' => config('services.stripe.webhook_secret'),
        ]);

        // 2. Route by Stripe event type
        $router = $workflow->addNode('Route by Event', 'switch', [
            'field' => '{{ item.type }}',
            'cases' => [
                ['port' => 'case_payment_succeeded', 'operator' => 'equals', 'value' => 'payment_intent.succeeded'],
                ['port' => 'case_payment_failed',    'operator' => 'equals', 'value' => 'payment_intent.payment_failed'],
                ['port' => 'case_refund',            'operator' => 'equals', 'value' => 'charge.refunded'],
            ],
            'fallthrough' => false, // Ignore unhandled event types
        ]);

        // --- Payment Succeeded Branch ---

        $updatePaid = $workflow->addNode('Mark Order Paid', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_payment_intent_id',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => [
                'status'  => 'paid',
                'paid_at' => '{{ now() }}',
            ],
        ]);

        $receiptEmail = $workflow->addNode('Send Receipt', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Payment received — ${{ item.data.object.amount / 100 }}',
            'body'    => 'Thank you for your payment of ${{ item.data.object.amount / 100 }}. Your order has been confirmed.',
            'is_html' => false,
        ]);

        // --- Payment Failed Branch ---

        $failureEmail = $workflow->addNode('Payment Failure Notice', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Payment failed — please update your card',
            'body'    => 'Your payment of ${{ item.data.object.amount / 100 }} could not be processed. Please update your payment method and try again.',
            'is_html' => false,
        ]);

        $retryCharge = $workflow->addNode('Retry Charge', 'http_request', [
            'url'    => 'https://api.stripe.com/v1/payment_intents/{{ item.data.object.id }}/confirm',
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer {{ env.STRIPE_SECRET_KEY }}',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 30,
        ]);

        // --- Refund Branch ---

        $updateRefunded = $workflow->addNode('Mark Order Refunded', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_charge_id',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => [
                'status'      => 'refunded',
                'refunded_at' => '{{ now() }}',
            ],
        ]);

        $refundEmail = $workflow->addNode('Refund Confirmation', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Refund processed — ${{ item.data.object.amount_refunded / 100 }}',
            'body'    => 'Your refund of ${{ item.data.object.amount_refunded / 100 }} has been processed. It may take 5-10 business days to appear on your statement.',
            'is_html' => false,
        ]);

        // Wire the graph
        $trigger->connect($router);

        // Payment succeeded path
        $router->connect($updatePaid, 'case_payment_succeeded');
        $updatePaid->connect($receiptEmail);

        // Payment failed path
        $router->connect($failureEmail, 'case_payment_failed');
        $failureEmail->connect($retryCharge);

        // Refund path
        $router->connect($updateRefunded, 'case_refund');
        $updateRefunded->connect($refundEmail);

        $workflow->activate();

        $this->info("Stripe webhook workflow created (ID: {$workflow->id})");
        $this->info("Webhook URL: /workflow-webhook/{$trigger->config['path']}");
    }
}
```

## Configuring Stripe

Point Stripe to the webhook URL generated by the workflow. The path UUID is assigned automatically when the node is created:

```
https://yourapp.com/workflow-webhook/a1b2c3d4-e5f6-7890-abcd-ef1234567890
```

In the Stripe dashboard, configure the webhook to send these event types:
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `charge.refunded`

## What Happens

1. **Webhook** receives the Stripe event payload via POST. Bearer authentication validates the webhook secret.
2. **Switch** reads `item.type` from the Stripe payload and routes to the matching case port.
3. **Payment succeeded path**: Updates the `Order` model status to `"paid"` (looked up by the Stripe payment intent ID), then sends a receipt email to the customer.
4. **Payment failed path**: Sends a failure notification email to the customer, then makes an HTTP POST to the Stripe API to retry confirming the payment intent.
5. **Refund path**: Updates the `Order` model status to `"refunded"` (looked up by the Stripe charge ID), then sends a refund confirmation email.
6. **Unhandled events**: With `fallthrough: false`, any Stripe event type not listed in the cases is silently dropped.

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Webhook trigger with bearer auth | Validates incoming Stripe requests with a shared secret |
| Switch node (multi-way routing) | Routes to three different handlers based on `item.type` |
| Fallthrough disabled | Unrecognized event types are ignored rather than routed to a default port |
| Update Model node | Finds orders by Stripe IDs and updates their status and timestamps |
| Nested field access | `{{ item.data.object.id }}` navigates Stripe's nested payload structure |
| Expression arithmetic | `{{ item.data.object.amount / 100 }}` converts cents to dollars inline |
| HTTP request to external API | Retries a Stripe payment intent via the Stripe REST API |
| Environment secrets | `{{ env.STRIPE_SECRET_KEY }}` keeps the Stripe key out of workflow config |


</div>
