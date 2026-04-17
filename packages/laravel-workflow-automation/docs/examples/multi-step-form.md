<div v-pre>

# Multi-Step Form Wizard

Collect data across multiple form steps using a workflow that pauses between each step. The first step collects personal information, the second collects payment details, and the workflow merges everything together before sending a confirmation email. Each step uses a Wait/Resume node with a 30-minute timeout.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - manual          Start the form process            |
|  - wait_resume(x2) Pause for each form step          |
|  - set_fields      Normalize submitted data          |
|  - merge           Combine data from all steps       |
|  - send_mail       Send confirmation email           |
|                                                      |
|  Concepts: multi-pause pattern, wait/resume with     |
|  timeout, resume with payload, resume token          |
|  retrieval, set_fields transformer, merge node,      |
|  REST API resume for frontend forms                  |
+------------------------------------------------------+
```

## Workflow Diagram

```text
    +------------------+     +------------------+     +------------------+
    |  Manual Trigger  | --> |  Wait/Resume:    | --> |  Set Fields:     |
    |  (form start)    |     |  Step 1 — info   |     |  normalize       |
    +------------------+     +------------------+     +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Wait/Resume:    |
                                                      |  Step 2 — pay    |
                                                      +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Merge:          |
                                                      |  combine all     |
                                                      +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Email:          |
                                                      |  confirmation    |
                                                      +------------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupMultiStepForm.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupMultiStepForm extends Command
{
    protected $signature = 'workflow:setup-multi-step-form';
    protected $description = 'Create the multi-step form wizard workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Multi-Step Form Wizard']);

        // 1. Manual trigger — starts the form process with metadata
        $trigger = $workflow->addNode('Start Form', 'manual');

        // 2. Wait for Step 1 — user submits personal information
        $step1 = $workflow->addNode('Step 1: Personal Info', 'wait_resume', [
            'timeout_seconds' => 1800, // 30 minutes
        ]);

        // 3. Normalize the data from Step 1
        $normalize = $workflow->addNode('Normalize Data', 'set_fields', [
            'fields' => [
                'email'        => '{{ lower(item.email) }}',
                'name'         => '{{ item.name }}',
                'submitted_at' => '{{ now() }}',
            ],
            'keep_existing' => true,
        ]);

        // 4. Wait for Step 2 — user submits payment information
        $step2 = $workflow->addNode('Step 2: Payment Info', 'wait_resume', [
            'timeout_seconds' => 1800, // 30 minutes
        ]);

        // 5. Merge all collected data into a single item
        $merge = $workflow->addNode('Combine Data', 'merge', [
            'mode' => 'wait_all',
        ]);

        // 6. Send confirmation email with all collected data
        $confirmation = $workflow->addNode('Confirmation Email', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Registration complete, {{ item.name }}!',
            'body'    => 'Hi {{ item.name }},\n\nYour registration is complete. Here is a summary:\n\nName: {{ item.name }}\nEmail: {{ item.email }}\nPlan: {{ item.plan }}\nCard ending in: {{ item.card_last4 }}\n\nThank you for signing up!',
            'is_html' => false,
        ]);

        // Wire the graph
        $trigger->connect($step1);
        $step1->connect($normalize, 'resume');
        $normalize->connect($step2);
        $step2->connect($merge, 'resume', 'main_1');
        $merge->connect($confirmation);

        $workflow->activate();

        $this->info("Multi-step form workflow created (ID: {$workflow->id})");
    }
}
```

## Triggering and Resuming the Workflow

### Step 0: Start the Form

Start the workflow when the user begins the form. Pass any initial metadata:

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

$workflow = Workflow::where('name', 'Multi-Step Form Wizard')->firstOrFail();

$run = $workflow->start([[
    'session_id' => 'sess_abc123',
    'started_at' => now()->toISOString(),
]]);

// $run->status === 'waiting' (paused at Step 1)
// Store $run->id in the user's session for later resume calls
```

### Step 1: Submit Personal Information

When the user completes the first form step, resume the workflow with their personal data:

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

Workflow::resume($runId, $token1, [
    'name'  => 'Alice Johnson',
    'email' => 'Alice@Example.com',
]);

// The Set Fields node normalizes the email to lowercase
// Then the workflow pauses again at Step 2
```

### Step 2: Submit Payment Information

When the user completes the payment step, resume again with payment details:

```php
Workflow::resume($runId, $token2, [
    'card_last4' => '4242',
    'plan'       => 'pro',
]);

// The workflow completes: data is merged and confirmation email is sent
```

### Retrieving Resume Tokens

Each Wait/Resume node generates a unique resume token stored in its node run output. Retrieve it after each pause:

```php
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;

// After starting the workflow, get Step 1's token:
$step1Run = WorkflowNodeRun::where('workflow_run_id', $runId)
    ->whereHas('node', fn ($q) => $q->where('name', 'Step 1: Personal Info'))
    ->first();

$token1 = $step1Run->output['resume']['resume_token'] ?? null;

// After resuming Step 1, get Step 2's token:
$step2Run = WorkflowNodeRun::where('workflow_run_id', $runId)
    ->whereHas('node', fn ($q) => $q->where('name', 'Step 2: Payment Info'))
    ->first();

$token2 = $step2Run->output['resume']['resume_token'] ?? null;
```

### Resume via REST API

You can also resume steps via the REST API, which is useful for frontend form submissions:

```bash
# Step 1: Submit personal info
POST /workflow-engine/runs/{runId}/resume
Content-Type: application/json

{
    "resume_token": "token-1-uuid...",
    "payload": {
        "name": "Alice Johnson",
        "email": "Alice@Example.com"
    }
}

# Step 2: Submit payment info
POST /workflow-engine/runs/{runId}/resume
Content-Type: application/json

{
    "resume_token": "token-2-uuid...",
    "payload": {
        "card_last4": "4242",
        "plan": "pro"
    }
}
```

## What Happens

1. **Manual trigger** starts the workflow with session metadata. The workflow immediately pauses at the first Wait/Resume node.
2. **Wait/Resume (Step 1)** pauses the run (status becomes `waiting`). A 30-minute timeout job is scheduled. The resume token is stored in the node run output.
3. **User submits Step 1** -- your application calls `Workflow::resume()` with the token and personal data (`name`, `email`). The resume payload is merged into the item.
4. **Set Fields** normalizes the data: email is lowercased via `{{ lower(item.email) }}`, and a `submitted_at` timestamp is added.
5. **Wait/Resume (Step 2)** pauses the run again. A new resume token is generated for this step.
6. **User submits Step 2** -- your application calls `Workflow::resume()` again with payment data (`card_last4`, `plan`). The payload is merged into the item.
7. **Merge** combines all data from the connected inputs into a single stream.
8. **Send Mail** sends a confirmation email to the user with all collected information: name, email, plan, and card details.

If either step times out (user abandons the form for 30+ minutes), the workflow ends via the `timeout` port. In this example the timeout ports are not connected, so the workflow simply ends. You could connect a reminder email or cleanup node to the timeout ports for better UX.

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Manual trigger | Starts the form process with initial session metadata |
| Wait/Resume node (twice) | Two sequential pause points collect data from separate form steps |
| Timeout on Wait/Resume | 30-minute timeout (1800 seconds) prevents abandoned forms from waiting forever |
| Resume with payload | Each step injects user-submitted data into the running workflow |
| Resume token retrieval | Tokens are stored in node run output and looked up between steps |
| Set Fields transformer | Normalizes email to lowercase and adds a timestamp between steps |
| Merge node | Combines data from multiple workflow stages into a single output |
| REST API resume | Alternative to PHP resume -- frontend can POST directly to the resume endpoint |
| Multi-pause pattern | Workflow pauses multiple times during a single run, collecting data incrementally |


</div>
