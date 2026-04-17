# Why Use This?

## Why Workflow Automation?

**Turn your Laravel app into a programmable automation platform — without touching your core code.**

- **AI-Agent Friendly** — Expose a REST API that AI agents can use to build and modify workflows. Agents change your app's behavior without editing a single PHP file.
- **No-Code Scenarios** — Non-technical team members build workflows from the visual editor. New business rule = new workflow, zero deployments.
- **Core Stays Clean** — Workflows live outside your application code. Add, change, or disable scenarios without modifying controllers, models, or routes.
- **Full Observability** — Every run is recorded with per-node input/output, duration, and errors. Trace failures, debug AI responses, replay any run.
- **Extensible** — One PHP class = one custom node. Internal APIs, domain logic, third-party services — all become reusable workflow building blocks.

## The Problem

Most Laravel apps start with automation logic baked directly into controllers, services, or event listeners. Over time the logic grows — fraud checks, tiered discounts, loyalty points, warehouse routing, Slack alerts — and a single method turns into hundreds of lines:

```php
// Scattered across your codebase...
class OrderController {
    public function store(Request $request) {
        $order = Order::create($request->validated());

        // Send confirmation email
        Mail::to($order->user)->send(new OrderConfirmation($order));

        // Fraud check via external API
        $fraud = Http::post('https://fraud.example.com/check', [
            'email' => $order->user->email,
            'total' => $order->total,
            'ip'    => $request->ip(),
        ]);

        if ($fraud->json('risk') === 'high') {
            $order->update(['status' => 'on_hold']);
            Mail::to('security@company.com')->send(new FraudAlert($order));
            return response()->json($order);
        }

        // Tiered discount: VIP customers get 10%, regulars get 5% on orders over $200
        if ($order->total > 200) {
            $discount = $order->user->is_vip ? 0.10 : 0.05;
            $order->applyDiscount($discount);
        }

        // Award loyalty points
        $order->user->increment('loyalty_points', (int) ($order->total / 10));

        // Notify Slack
        Http::post('https://hooks.slack.com/...', [
            'text' => "New order #{$order->id} — \${$order->total}",
        ]);

        // If high-value, notify manager and require approval
        if ($order->total > 1000) {
            Mail::to('manager@company.com')->send(new HighValueOrder($order));
            $order->update(['status' => 'pending_approval']);
        }

        // Route to the right warehouse
        $warehouse = $order->shipping_country === 'US'
            ? 'warehouse-us@company.com'
            : 'warehouse-eu@company.com';
        Mail::to($warehouse)->send(new FulfillmentRequest($order));

        // Log to analytics
        Http::post('https://analytics.example.com/events', [
            'event' => 'order_created',
            'total' => $order->total,
            'user'  => $order->user_id,
        ]);

        return response()->json($order);
    }
}
```

This works — until it doesn't:

- **Every change requires a developer.** Product wants to change the VIP discount from 10% to 15%? Add an SMS step for high-value orders? That's a code change, PR, review, deploy — every single time.
- **Logic is invisible.** No one can see the full automation flow without reading code. Non-technical team members are locked out.
- **No observability.** When the fraud check API times out or the Slack notification fails silently, you find out from a user complaint — not a dashboard.
- **Tightly coupled.** The controller now knows about emails, Slack, fraud APIs, loyalty points, warehouse routing, analytics, and business rules. Testing means mocking eight external services. Refactoring is terrifying.
- **AI can't help.** An AI agent can't safely modify this method — one wrong edit in a 60-line controller could break your entire checkout flow.

## The Solution

Laravel Workflow Automation separates your automation logic from your application code. Instead of writing automation in PHP, you **compose workflows from reusable nodes** — either through the visual editor or the REST API.

Your controller becomes clean:

```php
class OrderController {
    public function store(Request $request) {
        $order = Order::create($request->validated());
        return response()->json($order);
    }
}
```

The automation moves into a workflow — created once via PHP, the visual editor, or the REST API:

```php
$workflow = Workflow::create(['name' => 'Order Automation']);

$trigger      = $workflow->addNode('Order Created',      'model_event',   ['model' => Order::class, 'events' => ['created']]);
$confirm      = $workflow->addNode('Confirmation Email',  'send_mail',     ['to' => '{{ item.user.email }}', 'subject' => 'Order #{{ item.id }} confirmed']);
$fraudCheck   = $workflow->addNode('Fraud Check',         'http_request',  ['url' => 'https://fraud.example.com/check', 'method' => 'POST']);
$fraudGate    = $workflow->addNode('High Risk?',          'if_condition',  ['field' => '{{ nodes.Fraud Check.risk }}', 'operator' => '==', 'value' => 'high']);
$holdOrder    = $workflow->addNode('Hold Order',          'update_model',  ['model' => Order::class, 'fields' => ['status' => 'on_hold']]);
$securityMail = $workflow->addNode('Alert Security',      'send_mail',     ['to' => 'security@company.com', 'subject' => 'Fraud alert: Order #{{ item.id }}']);
$slack        = $workflow->addNode('Slack Notify',        'http_request',  ['url' => 'https://hooks.slack.com/...', 'method' => 'POST']);
$highValue    = $workflow->addNode('High Value?',         'if_condition',  ['field' => '{{ item.total }}', 'operator' => '>', 'value' => '1000']);
$manager      = $workflow->addNode('Notify Manager',      'send_mail',     ['to' => 'manager@company.com', 'subject' => 'High-value order #{{ item.id }}']);
$warehouse    = $workflow->addNode('US Customer?',        'if_condition',  ['field' => '{{ item.shipping_country }}', 'operator' => '==', 'value' => 'US']);
$warehouseUS  = $workflow->addNode('Fulfill US',          'send_mail',     ['to' => 'warehouse-us@company.com']);
$warehouseEU  = $workflow->addNode('Fulfill EU',          'send_mail',     ['to' => 'warehouse-eu@company.com']);
$analytics    = $workflow->addNode('Analytics',           'http_request',  ['url' => 'https://analytics.example.com/events', 'method' => 'POST']);

// Connect the flow
$trigger->connect($confirm)->connect($fraudCheck)->connect($fraudGate);

$fraudGate->connect($holdOrder, 'true');        // high-risk → hold & alert
$holdOrder->connect($securityMail);

$fraudGate->connect($slack, 'false');           // normal → continue
$slack->connect($highValue);
$highValue->connect($manager, 'true');          // high-value → notify manager
$manager->connect($warehouse);
$highValue->connect($warehouse, 'false');       // normal → warehouse
$warehouse->connect($warehouseUS, 'true');
$warehouse->connect($warehouseEU, 'false');
$warehouseUS->connect($analytics);
$warehouseEU->connect($analytics);

$workflow->activate();
```

Same behavior, but now it's **visible**, **editable**, **observable**, and **manageable** — without touching the controller again.

## Key Benefits

### AI-Agent Friendly

The package exposes a complete REST API for workflow management. AI agents can:

- Create workflows (`POST /workflows`)
- Add and connect nodes (`POST /workflows/{id}/nodes`, `POST /workflows/{id}/edges`)
- Configure node behavior (`PUT /workflows/{id}/nodes/{id}`)
- Execute and monitor runs (`POST /workflows/{id}/run`, `GET /runs/{id}`)

This means an AI agent can **modify your application's behavior** without editing a single PHP file. The agent works within a safe, bounded interface — it can only compose workflows from registered node types, never execute arbitrary code in your core.

### No-Code Scenarios

The built-in [visual editor](/ui-editor) lets non-technical team members build and modify workflows directly:

- Drag nodes onto a canvas
- Connect them visually
- Configure each node through dynamic forms
- Test with real data and see results immediately

New business rule? New workflow — zero deployments, zero developer time.

### Core Stays Clean

Workflows live entirely outside your application code:

| Traditional | With Workflow Automation |
|-------------|------------------------|
| Logic in controllers, services, listeners | Logic in workflow definitions (database) |
| Change = code edit + PR + deploy | Change = edit workflow in UI or API |
| Disable = comment out code | Disable = toggle workflow off |
| Rollback = git revert + deploy | Rollback = deactivate or delete workflow |

Your models, controllers, and routes stay focused on their primary responsibility.

### Full Observability

Every workflow execution is recorded with:

- **Per-node status** — completed, failed, running, skipped
- **Input/output data** — full JSON for every node in the chain
- **Duration** — how long each node took
- **Error details** — exact error messages for failed nodes
- **Replay** — re-execute any run with the same or modified payload

No more guessing why an email didn't send or which step in the chain failed.

### Extensible

Adding a custom node is one PHP class:

```php
#[AsWorkflowNode(
    key: 'notify_crm',
    name: 'Notify CRM',
    type: NodeType::Action,
)]
class NotifyCrmNode extends BaseNode
{
    public function execute(WorkflowNodeRun $nodeRun, array $input): array
    {
        $response = Http::post('https://crm.example.com/api/events', [
            'event' => $this->config('event_type'),
            'data'  => $input['item'],
        ]);

        return ['crm_response' => $response->json()];
    }
}
```

Once created, it's automatically available in the visual editor, REST API, and node registry. Internal APIs, domain-specific logic, third-party integrations — all become reusable building blocks.

## AI Agents as First-Class Citizens

This is where the package opens a fundamentally new door. Consider what happens when you give an AI agent access to the workflow API:

**Traditional approach:** You ask an AI agent to add a feature to your app. The agent needs to understand your codebase, find the right files, write PHP code, and hope it doesn't break anything.

**With Workflow Automation:** The agent doesn't touch your code at all. It creates a workflow through the REST API:

```
Agent receives: "When a customer signs up, wait 3 days,
                 check if they've used the product,
                 and send an onboarding or reminder email."

Agent executes:
  1. POST /workflows                        → Create "Onboarding Flow"
  2. POST /workflows/1/nodes                → Add Model Event Trigger (User created)
  3. POST /workflows/1/nodes                → Add Delay node (3 days)
  4. POST /workflows/1/nodes                → Add HTTP Request (check usage API)
  5. POST /workflows/1/nodes                → Add IF Condition (has_usage == true)
  6. POST /workflows/1/nodes                → Add Send Mail (onboarding)
  7. POST /workflows/1/nodes                → Add Send Mail (reminder)
  8. POST /workflows/1/edges                → Connect all nodes
  9. POST /workflows/1/activate             → Go live
```

No PHP files opened. No deploy needed. And if it's wrong:
- **Safe** — The agent can only use registered node types, never arbitrary code
- **Bounded** — It operates through the API, not your filesystem
- **Reversible** — Deactivate or delete the workflow, app returns to normal
- **Auditable** — Every run is logged with full input/output

## Use Cases

### Customer Onboarding

```
┌  Model Event (User created)
│
├─ Delay (3 days)
│
├─ HTTP Request
│  GET /api/usage?user={{ item.id }}
│
◇─ IF Condition
│  usage_count > 0
│
├─ true  → Send Mail (onboarding tips)
│          to: {{ item.email }}
│
├─ false → Send Mail (reminder)
│          "We noticed you haven't tried..."
│
└  Done
```

### Lead Scoring with AI

```
┌  Webhook (form submitted)
│
├─ AI Node
│  "Score this lead 0-100: {{ item }}"
│
◇─ IF Condition
│  ai_score > 80
│
├─ true  → HTTP Request (create CRM deal)
│        → Send Mail (sales team alert)
│
├─ false → Send Mail (nurture sequence)
│
└  Done
```

### Invoice Approval

```
┌  Webhook (invoice.created)
│
◇─ IF Condition
│  item.total > 1000
│
├─ true  → Send Mail (manager approval request)
│        → Wait / Resume (approval token)
│        → Update Model (invoice.status = approved)
│
├─ false → Update Model (invoice.status = auto_approved)
│
└  Done
```

### Email Drip Campaign

```
┌  Model Event (User created)
│
├─ Delay (1 day)
├─ Send Mail — "Welcome to the platform"
│
├─ Delay (3 days)
├─ Send Mail — "Here are 3 tips to get started"
│
├─ Delay (7 days)
├─ Send Mail — "Ready to upgrade?"
│
└  Done
```

### Error Alerting

```
┌  Schedule (every 5 minutes)
│
├─ HTTP Request
│  GET https://api.example.com/health
│
◇─ IF Condition
│  status != 200
│
├─ true  → HTTP Request (Slack webhook)
│          "Health check failed: {{ item.status }}"
│        → Send Mail (ops team)
│
├─ false → (no action)
│
└  Done
```

### Stripe Webhook Handler

```
┌  Webhook (stripe event)
│
◇─ Switch (item.type)
│
├─ invoice.paid      → Update Model (subscription.status = active)
│                    → Send Mail (payment receipt)
│
├─ invoice.failed    → Send Mail (payment failed warning)
│                    → HTTP Request (Slack alert)
│
├─ customer.deleted  → Update Model (user.status = churned)
│                    → Send Mail (offboarding)
│
└  Done
```

## When to Use

**Good fit:**

- Automation flows that change frequently (marketing campaigns, notification rules, onboarding sequences)
- Processes that non-technical team members need to manage
- AI-driven scenarios where agents need to create or modify app behavior
- Multi-step processes with conditions, delays, and external API calls
- Anything that needs full execution logging and replay capability

**Not the right fit:**

- Core business logic that rarely changes and is performance-critical (use plain PHP)
- Simple one-off tasks (a single Artisan command is simpler)
- Real-time, sub-millisecond processing (workflows add overhead from graph traversal and logging)

## Next Steps

Ready to get started? Head to [Installation](/getting-started/installation) and have your first workflow running in under 5 minutes.
