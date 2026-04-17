# Purchase Approval (Human-in-the-Loop)

Route purchase requests through an approval process. Small purchases under $1,000 are auto-approved. Large purchases pause the workflow, email the manager for approval, and wait up to 3 days for a response. The workflow resumes when the manager clicks approve or reject, or times out.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - manual          Trigger from application code     |
|  - if_condition    Route by purchase amount           |
|  - send_mail (x4)  Ask manager, auto-approve,        |
|                    approved, rejected notifications   |
|  - wait_resume     Pause for manager decision        |
|                                                      |
|  Concepts: human-in-the-loop, wait/resume with       |
|  timeout, resume with payload, REST API resume,      |
|  resume token retrieval, dual-branch IF routing      |
+------------------------------------------------------+
```

## Workflow Diagram

```text
                          +------------------------+
                          |  Manual Trigger         |
                          |  (purchase request)     |
                          +----------+-------------+
                                     |
                                     v
                          +----------+-------------+
                          |  IF: amount > 1000     |
                          +----+--------------+----+
                               |              |
                            true            false
                               |              |
                               v              v
                    +----------+----------+   +----------+-----------+
                    |  Send Mail:         |   |  Send Mail:          |
                    |  ask manager        |   |  auto-approved       |
                    +----------+----------+   +----------------------+
                               |
                               v
                    +----------+----------+
                    |  Wait / Resume      |
                    |  (3-day timeout)    |
                    +----+----------+----+
                         |          |
                      resume     timeout
                         |          |
                         v          v
              +----------+---+   (workflow ends,
              | IF: approved |    item timed out)
              +---+------+---+
                  |      |
               true    false
                  |      |
                  v      v
          +-------+--+ +-+----------+
          | Email:   | | Email:     |
          | approved | | rejected   |
          +----------+ +------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupApprovalWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupApprovalWorkflow extends Command
{
    protected $signature = 'workflow:setup-approvals';
    protected $description = 'Create the purchase approval workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Purchase Approval']);

        // 1. Manual trigger — receives the purchase request data
        $trigger = $workflow->addNode('Purchase Request', 'manual');

        // 2. Check if the amount exceeds $1,000
        $amountCheck = $workflow->addNode('Amount Check', 'if_condition', [
            'field'    => '{{ item.amount }}',
            'operator' => 'greater_than',
            'value'    => '1000',
        ]);

        // 3a. Large purchase — email the manager for approval
        $askManager = $workflow->addNode('Ask Manager', 'send_mail', [
            'to'      => '{{ item.manager_email }}',
            'subject' => 'Approval needed: ${{ item.amount }} purchase by {{ item.requester }}',
            'body'    => 'A purchase request of ${{ item.amount }} was submitted by {{ item.requester }}. Description: {{ item.description }}. Please approve or reject using the link in your dashboard.',
            'is_html' => false,
        ]);

        // 4. Wait for the manager to respond (up to 3 days)
        $wait = $workflow->addNode('Await Decision', 'wait_resume', [
            'timeout_seconds' => 259200, // 3 days
        ]);

        // 5. Check the manager's response
        $approvalCheck = $workflow->addNode('Approved?', 'if_condition', [
            'field'    => '{{ item.approved }}',
            'operator' => 'equals',
            'value'    => 'true',
        ]);

        // 6a. Approved — notify the requester
        $approvedEmail = $workflow->addNode('Approved Notification', 'send_mail', [
            'to'      => '{{ item.requester_email }}',
            'subject' => 'Purchase approved: ${{ item.amount }}',
            'body'    => 'Your purchase request for ${{ item.amount }} has been approved by your manager.',
        ]);

        // 6b. Rejected — notify the requester
        $rejectedEmail = $workflow->addNode('Rejected Notification', 'send_mail', [
            'to'      => '{{ item.requester_email }}',
            'subject' => 'Purchase rejected: ${{ item.amount }}',
            'body'    => 'Your purchase request for ${{ item.amount }} has been rejected. Please contact your manager for details.',
        ]);

        // 3b. Small purchase — auto-approve immediately
        $autoApproved = $workflow->addNode('Auto-Approved', 'send_mail', [
            'to'      => '{{ item.requester_email }}',
            'subject' => 'Purchase auto-approved: ${{ item.amount }}',
            'body'    => 'Your purchase request for ${{ item.amount }} has been automatically approved (under $1,000 threshold).',
        ]);

        // Wire the graph
        $trigger->connect($amountCheck);
        $amountCheck->connect($askManager, 'true');         // Over $1,000
        $amountCheck->connect($autoApproved, 'false');      // Under $1,000
        $askManager->connect($wait);
        $wait->connect($approvalCheck, 'resume');
        $approvalCheck->connect($approvedEmail, 'true');
        $approvalCheck->connect($rejectedEmail, 'false');

        $workflow->activate();

        $this->info("Approval workflow created (ID: {$workflow->id})");
    }
}
```

## Triggering the Workflow

Start the workflow from your application when a purchase request is submitted:

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

$workflow = Workflow::where('name', 'Purchase Approval')->firstOrFail();

$run = $workflow->start([[
    'requester'       => 'Alice Johnson',
    'requester_email' => 'alice@company.com',
    'manager_email'   => 'bob.manager@company.com',
    'amount'          => 2500,
    'description'     => 'New laptop for development team',
]]);

// For amounts > $1000, the run pauses:
// $run->status === 'waiting'
```

## Resuming the Workflow

When the manager makes a decision, resume the workflow by providing the run ID, resume token, and the decision payload.

### Option A: Resume via PHP

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

// Approve
Workflow::resume($runId, $resumeToken, ['approved' => true]);

// Reject
Workflow::resume($runId, $resumeToken, ['approved' => false]);
```

### Option B: Resume via REST API

```bash
POST /workflow-engine/runs/{runId}/resume
Content-Type: application/json

{
    "resume_token": "a1b2c3d4-...",
    "payload": {
        "approved": true
    }
}
```

The `resume_token` is available in the node run output of the Wait/Resume node. You can look it up via:

```php
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;

$nodeRun = WorkflowNodeRun::where('workflow_run_id', $runId)
    ->whereHas('node', fn ($q) => $q->where('name', 'Await Decision'))
    ->first();

$resumeToken = $nodeRun->output['resume']['resume_token'] ?? null;
```

## What Happens

1. **Manual trigger** receives the purchase request with requester info, manager email, amount, and description.
2. **IF Condition** checks if `amount > 1000`. A $2,500 request goes to the `true` branch.
3. **Send Mail (ask manager)** emails the manager with the request details, asking them to approve or reject.
4. **Wait/Resume** pauses the workflow. The run status changes to `waiting`. A timeout job is scheduled for 3 days (259,200 seconds).
5. **Manager responds** -- your application calls `Workflow::resume()` with `['approved' => true]` or `['approved' => false]`. The resume payload is merged into the item.
6. **IF Condition (approved?)** checks the `approved` field from the resume payload.
7. **Approved email** or **Rejected email** is sent to the requester based on the manager's decision.

If the manager does not respond within 3 days, the timeout fires and the workflow ends via the `timeout` port (no further action in this example, but you could connect a reminder or escalation node there).

For amounts under $1,000, the workflow skips the approval entirely and sends an auto-approved notification immediately.

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Manual trigger | Purchase request data is pushed from application code |
| IF condition branching | Routes to manager approval or auto-approval based on amount |
| Wait/Resume node | Pauses workflow execution until an external event (manager decision) |
| Timeout handling | 3-day timeout (259,200 seconds) prevents workflows from waiting forever |
| Resume with payload | Manager's decision (`approved: true/false`) is injected when resuming |
| REST API resume | Alternative to PHP -- resume via HTTP endpoint with token and payload |
| Human-in-the-loop pattern | Workflow pauses for human decision, then continues based on response |
| Sequential node chaining | Email -> Wait -> Condition -> Result email forms a linear approval chain |
