# User Onboarding

Automatically welcome new users with a tailored email based on how they signed up, triggered by Eloquent model events.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - model_event      Trigger on User::created         |
|  - switch           Route by signup source field     |
|  - send_mail (x3)   Source-specific welcome emails   |
|  - http_request     Credit referrer via external API |
|                                                      |
|  Concepts: model event auto-trigger, switch routing  |
|  with named ports, fallthrough to default, env       |
|  variable secrets, sequential chaining               |
+------------------------------------------------------+
```

## Workflow Diagram

```text
[User Created]     -->     [Switch: source]
                        |          |          |
                  case_organic  case_referral  default
                        |          |          |
                        v          v          v
                [Email:       [Email:       [Email:
                 Product       Referral      Generic
                 Tour]         Welcome]      Greeting]
                                  |
                                  v
                            [HTTP POST:
                             Credit Referrer API]
```

Each signup source gets exactly one branch -- items are never duplicated across switch ports.

## Complete Workflow Setup

```php
// app/Console/Commands/SetupOnboardingWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupOnboardingWorkflow extends Command
{
    protected $signature = 'workflow:setup-onboarding';
    protected $description = 'Create the user onboarding workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'User Onboarding']);

        // 1. Trigger when a new User model is created
        $trigger = $workflow->addNode('User Created', 'model_event', [
            'model'  => 'App\\Models\\User',
            'events' => ['created'],
        ]);

        // 2. Route based on the signup source field
        $router = $workflow->addNode('Route by Source', 'switch', [
            'field' => '{{ item.source }}',
            'cases' => [
                ['port' => 'case_organic',  'operator' => 'equals', 'value' => 'organic'],
                ['port' => 'case_referral', 'operator' => 'equals', 'value' => 'referral'],
            ],
            'fallthrough' => true,
        ]);

        // 3a. Organic users -- product tour email
        $organicEmail = $workflow->addNode('Organic Welcome', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Welcome, {{ item.name }}! Here is your product tour',
            'body'    => 'Hi {{ item.name }}, thanks for finding us! Here are 3 things to try first...',
        ]);

        // 3b. Referral users -- referral-specific welcome email
        $referralEmail = $workflow->addNode('Referral Welcome', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Welcome, {{ item.name }}! Your friend sent you here',
            'body'    => 'Hi {{ item.name }}, welcome aboard! Your referrer {{ item.referred_by }} will receive a credit shortly.',
        ]);

        // 3b-cont. Credit the referrer via external API
        $creditReferrer = $workflow->addNode('Credit Referrer', 'http_request', [
            'url'    => 'https://api.example.com/referrals/credit',
            'method' => 'POST',
            'body'   => [
                'referrer_code' => '{{ item.referred_by }}',
                'new_user_id'   => '{{ item.id }}',
            ],
            'headers' => [
                'Authorization' => 'Bearer {{ env.REFERRAL_API_TOKEN }}',
            ],
            'timeout' => 10,
        ]);

        // 3c. Default -- generic welcome for unknown sources
        $genericEmail = $workflow->addNode('Generic Welcome', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Welcome to our platform, {{ item.name }}!',
            'body'    => 'Hi {{ item.name }}, thanks for signing up. Let us know if you need help getting started.',
        ]);

        // Wire the graph
        $trigger->connect($router);
        $router->connect($organicEmail, 'case_organic');
        $router->connect($referralEmail, 'case_referral');
        $router->connect($genericEmail, 'default');
        $referralEmail->connect($creditReferrer);

        $workflow->activate();

        $this->info("Onboarding workflow created (ID: {$workflow->id})");
    }
}
```

## Registering the Model Event Listener

Add the listener registration to your `AppServiceProvider` so that Eloquent model events automatically trigger workflows:

```php
// app/Providers/AppServiceProvider.php

use Aftandilmmd\WorkflowAutomation\Listeners\ModelEventListener;

public function boot(): void
{
    ModelEventListener::register();
}
```

## Triggering the Workflow

The workflow fires automatically when a new `User` is created. No manual `start()` call is needed:

```php
use App\Models\User;

// Organic signup -> sends product tour email
User::create([
    'name'     => 'Alice',
    'email'    => 'alice@example.com',
    'password' => bcrypt('secret'),
    'source'   => 'organic',
]);

// Referral signup -> sends referral email, then credits referrer via API
User::create([
    'name'        => 'Bob',
    'email'       => 'bob@example.com',
    'password'    => bcrypt('secret'),
    'source'      => 'referral',
    'referred_by' => 'REF-CHARLIE-42',
]);

// Unknown source -> sends generic welcome email (default branch)
User::create([
    'name'     => 'Charlie',
    'email'    => 'charlie@example.com',
    'password' => bcrypt('secret'),
    'source'   => 'ad_campaign',
]);
```

## How It Works

Step-by-step data flow for a referral signup:

1. **Model Event trigger** fires when `User::create()` is called. The model's `toArray()` data becomes the workflow item, including `name`, `email`, `source`, and `referred_by`.

2. **Switch** reads `item.source` and evaluates cases in order. `"organic"` matches `case_organic`, `"referral"` matches `case_referral`, and anything else (like `"ad_campaign"`) falls through to `default`.

3. **Organic path** sends a product-tour email to the new user and ends. No further nodes.

4. **Referral path** sends a referral-themed welcome email, then continues to the HTTP node which POSTs to the referral credit API with `referred_by` and the new user's `id`.

5. **Default path** sends a generic welcome email and ends. Any source value not explicitly listed lands here.

| Signup | Source Field | Switch Port | Actions |
|--------|-------------|-------------|---------|
| Alice | `organic` | `case_organic` | Product tour email |
| Bob | `referral` | `case_referral` | Referral email + credit API call |
| Charlie | `ad_campaign` | `default` | Generic welcome email |
