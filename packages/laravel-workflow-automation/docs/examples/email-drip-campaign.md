<div v-pre>

# Email Drip Campaign

Run a 7-day drip campaign for new signups. A scheduled workflow runs daily, fetches users who signed up yesterday, and sends each user a sequence of three emails spaced over a week: a welcome email on day 1, tips on day 3, and a special offer on day 7. Delay nodes handle the timing between sends.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - schedule        Cron trigger at 9 AM daily        |
|  - http_request    Fetch yesterday's new signups     |
|  - loop            Iterate over each user            |
|  - send_mail (x3)  Welcome, tips, and offer emails   |
|  - delay (x2)      72-hour and 96-hour pauses        |
|                                                      |
|  Concepts: schedule trigger, loop with _loop_item,   |
|  delay node (queue-based), chained delays,           |
|  multi-email drip sequence over 7 days               |
+------------------------------------------------------+
```

## Workflow Diagram

```text
    +------------------+     +------------------+     +------------------+
    |  Schedule:       | --> |  HTTP GET:       | --> |  Loop:           |
    |  daily at 9 AM   |     |  fetch signups   |     |  each user       |
    +------------------+     +------------------+     +--------+---------+
                                                               | loop_item
                                                               v
                                                      +--------+---------+
                                                      |  Email:          |
                                                      |  Day 1 Welcome   |
                                                      +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Delay:          |
                                                      |  3 days (72h)    |
                                                      +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Email:          |
                                                      |  Day 3 Tips      |
                                                      +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Delay:          |
                                                      |  4 days (96h)    |
                                                      +--------+---------+
                                                               |
                                                               v
                                                      +--------+---------+
                                                      |  Email:          |
                                                      |  Day 7 Offer     |
                                                      +------------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupDripCampaign.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupDripCampaign extends Command
{
    protected $signature = 'workflow:setup-drip-campaign';
    protected $description = 'Create the email drip campaign workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Email Drip Campaign']);

        // 1. Schedule trigger — runs every day at 9:00 AM
        $trigger = $workflow->addNode('Daily 9 AM', 'schedule', [
            'interval_type' => 'custom_cron',
            'cron'          => '0 9 * * *',
        ]);

        // 2. Fetch users who signed up yesterday
        $fetchSignups = $workflow->addNode('Fetch New Signups', 'http_request', [
            'url'              => 'https://api.yourapp.com/api/signups?date=yesterday',
            'method'           => 'GET',
            'headers'          => ['Authorization' => 'Bearer {{ env.APP_API_TOKEN }}'],
            'timeout'          => 15,
            'include_response' => true,
        ]);

        // 3. Loop through each new user
        $loop = $workflow->addNode('Each User', 'loop', [
            'source_field' => 'users',
        ]);

        // 4. Day 1 — Welcome email (sent immediately)
        $welcomeEmail = $workflow->addNode('Day 1: Welcome', 'send_mail', [
            'to'      => '{{ item._loop_item.email }}',
            'subject' => 'Welcome to our platform, {{ item._loop_item.name }}!',
            'body'    => 'Hi {{ item._loop_item.name }},\n\nWelcome aboard! We are excited to have you. Here is what you can do to get started:\n\n1. Complete your profile\n2. Explore the dashboard\n3. Connect your first integration\n\nSee you around!',
            'is_html' => false,
        ]);

        // 5. Wait 3 days before the next email
        $delay1 = $workflow->addNode('Wait 3 Days', 'delay', [
            'delay_type'  => 'hours',
            'delay_value' => 72,
        ]);

        // 6. Day 3 — Tips and tricks email
        $tipsEmail = $workflow->addNode('Day 3: Tips', 'send_mail', [
            'to'      => '{{ item._loop_item.email }}',
            'subject' => '3 tips to get the most out of your account, {{ item._loop_item.name }}',
            'body'    => 'Hi {{ item._loop_item.name }},\n\nYou have been with us for 3 days! Here are some tips:\n\n- Tip 1: Use keyboard shortcuts to save time\n- Tip 2: Set up notifications for important events\n- Tip 3: Check out our API docs for advanced integrations\n\nHappy building!',
            'is_html' => false,
        ]);

        // 7. Wait 4 more days (day 3 → day 7)
        $delay2 = $workflow->addNode('Wait 4 Days', 'delay', [
            'delay_type'  => 'hours',
            'delay_value' => 96,
        ]);

        // 8. Day 7 — Special offer email
        $offerEmail = $workflow->addNode('Day 7: Special Offer', 'send_mail', [
            'to'      => '{{ item._loop_item.email }}',
            'subject' => 'A special offer just for you, {{ item._loop_item.name }}',
            'body'    => 'Hi {{ item._loop_item.name }},\n\nYou have been exploring our platform for a week now. To thank you, here is an exclusive 20% discount on any Pro plan:\n\nUse code: WELCOME20\n\nThis offer expires in 48 hours. Upgrade now and unlock all features!\n\nCheers!',
            'is_html' => false,
        ]);

        // Wire the graph
        $trigger->connect($fetchSignups);
        $fetchSignups->connect($loop);
        $loop->connect($welcomeEmail, 'loop_item');
        $welcomeEmail->connect($delay1);
        $delay1->connect($tipsEmail);
        $tipsEmail->connect($delay2);
        $delay2->connect($offerEmail);

        $workflow->activate();

        $this->info("Drip campaign workflow created (ID: {$workflow->id})");
    }
}
```

## Laravel Scheduler Setup

Register the schedule runner so the workflow fires on time:

```php
// routes/console.php

Schedule::command('workflow:schedule-run')->everyMinute();
```

Also make sure a queue worker is running so that delay jobs fire correctly:

```bash
php artisan queue:work --sleep=3
```

## What Happens

1. **Schedule trigger** fires at 9:00 AM daily. The `workflow:schedule-run` command detects the due schedule and starts a new run.
2. **HTTP GET** fetches users who signed up yesterday from the application API. The response contains a `users` array.
3. **Loop** iterates over the `users` array. Each user becomes a separate item with `_loop_item` containing their `name`, `email`, and other fields.
4. **Day 1 Welcome email** is sent immediately to each user. The subject and body use `{{ item._loop_item.name }}` and `{{ item._loop_item.email }}`.
5. **Delay (3 days)** pauses the workflow run. The run status becomes `waiting`, and a `ResumeWorkflowJob` is dispatched with a 72-hour delay. The engine does not block -- it exits cleanly.
6. **Day 3 Tips email** fires when the queue worker picks up the delayed job 3 days later. Tips are sent to each user.
7. **Delay (4 days)** pauses again for another 96 hours.
8. **Day 7 Special Offer email** fires 7 days after the original signup, completing the drip sequence.

### Timeline per User

| Day | Event | Node |
|-----|-------|------|
| Day 1 | Welcome email sent | Day 1: Welcome |
| Day 1-3 | Workflow paused | Wait 3 Days |
| Day 3 | Tips email sent | Day 3: Tips |
| Day 3-7 | Workflow paused | Wait 4 Days |
| Day 7 | Special offer sent | Day 7: Special Offer |

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Schedule trigger (custom_cron) | `0 9 * * *` fires the workflow at 9 AM daily to process yesterday's signups |
| HTTP request for data fetching | GETs new signup data from an internal API before processing |
| Loop node | Iterates over the `users` array so each user gets their own drip sequence |
| Loop item expressions | `{{ item._loop_item.email }}` and `{{ item._loop_item.name }}` address each user |
| Delay node (queue-based) | 72-hour and 96-hour delays use `ResumeWorkflowJob::dispatch()->delay()` -- never `sleep()` |
| Chained delays | Two delay nodes create a 3-day gap then a 4-day gap (7 days total) |
| Queue worker dependency | Delays require `php artisan queue:work` to be running for jobs to fire on time |
| Multi-email sequence | Three send_mail nodes deliver a coordinated series of emails over time |


</div>
