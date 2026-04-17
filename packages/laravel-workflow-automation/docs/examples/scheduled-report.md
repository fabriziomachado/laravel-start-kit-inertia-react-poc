<div v-pre>

# Scheduled Daily Report

Generate and email a daily sales report every morning at 8 AM. The workflow fetches sales data from an internal API, filters out departments with zero revenue, aggregates totals by department, and sends the summary to the leadership team.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - schedule        Cron trigger at 8 AM daily        |
|  - http_request    Fetch sales data from internal API|
|  - filter          Remove zero-revenue departments   |
|  - aggregate       Group by department, sum/avg      |
|  - send_mail       Email report per department       |
|                                                      |
|  Concepts: schedule trigger with custom_cron, date   |
|  expressions in URLs, filter utility, aggregate      |
|  with group_by and multiple operations, per-item     |
|  email delivery                                      |
+------------------------------------------------------+
```

## Workflow Diagram

```text
    +------------------+     +------------------+     +------------------+
    |  Schedule:       | --> |  HTTP GET:       | --> |  Filter:         |
    |  8 AM daily      |     |  fetch sales     |     |  non-zero only   |
    +------------------+     +------------------+     +------------------+
                                                              |
                                                              v
                                                     +------------------+
                                                     |  Aggregate:      |
                                                     |  by department   |
                                                     +--------+---------+
                                                              |
                                                              v
                                                     +--------+---------+
                                                     |  Email:          |
                                                     |  daily report    |
                                                     +------------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupDailyReport.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupDailyReport extends Command
{
    protected $signature = 'workflow:setup-daily-report';
    protected $description = 'Create the scheduled daily sales report workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Daily Sales Report']);

        // 1. Schedule trigger — fires every day at 8:00 AM
        $trigger = $workflow->addNode('Daily 8 AM', 'schedule', [
            'interval_type' => 'custom_cron',
            'cron'          => '0 8 * * *',
        ]);

        // 2. Fetch sales data from the internal API
        $fetchSales = $workflow->addNode('Fetch Sales', 'http_request', [
            'url'              => 'https://api.internal.com/sales?date={{ date_format(now(), "Y-m-d") }}',
            'method'           => 'GET',
            'headers'          => ['Authorization' => 'Bearer {{ env.INTERNAL_API_TOKEN }}'],
            'timeout'          => 30,
            'include_response' => true,
        ]);

        // 3. Filter out departments with zero revenue
        $filter = $workflow->addNode('Non-Zero Revenue', 'filter', [
            'conditions' => [
                ['field' => 'revenue', 'operator' => 'greater_than', 'value' => '0'],
            ],
            'logic' => 'and',
        ]);

        // 4. Aggregate revenue and order count by department
        $aggregate = $workflow->addNode('By Department', 'aggregate', [
            'group_by'   => 'department',
            'operations' => [
                ['field' => 'revenue',     'function' => 'sum', 'alias' => 'total_revenue'],
                ['field' => 'order_count', 'function' => 'sum', 'alias' => 'total_orders'],
                ['field' => 'revenue',     'function' => 'avg', 'alias' => 'avg_order_value'],
            ],
        ]);

        // 5. Email the aggregated report
        $report = $workflow->addNode('Send Report', 'send_mail', [
            'to'      => 'leadership@company.com',
            'subject' => 'Daily Sales Report — {{ date_format(now(), "M d, Y") }}',
            'body'    => 'Department: {{ item.department }}\nTotal Revenue: ${{ item.total_revenue }}\nTotal Orders: {{ item.total_orders }}\nAvg Order Value: ${{ item.avg_order_value }}',
            'is_html' => false,
        ]);

        // Wire the graph
        $trigger->connect($fetchSales);
        $fetchSales->connect($filter);
        $filter->connect($aggregate);
        $aggregate->connect($report);

        $workflow->activate();

        $this->info("Daily report workflow created (ID: {$workflow->id})");
    }
}
```

## Laravel Scheduler Setup

The schedule trigger requires the `workflow:schedule-run` command to be registered in your Laravel scheduler:

```php
// routes/console.php

Schedule::command('workflow:schedule-run')->everyMinute();
```

Make sure the Laravel scheduler cron is running on your server:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## What Happens

1. **Schedule trigger** fires at 8:00 AM every day. The `workflow:schedule-run` command checks all active schedule triggers each minute and dispatches due workflows.
2. **HTTP GET** calls the internal sales API with today's date. The response data is merged into the item.
3. **Filter** removes any departments that had zero revenue, keeping only departments with actual sales.
4. **Aggregate** groups the remaining records by `department` and computes three metrics: `sum` of revenue, `sum` of order count, and `avg` of revenue (average order value).
5. **Send Mail** sends one email per aggregated department row to the leadership team with the summary.

### Example Data Flow

| Stage | Data |
|-------|------|
| Schedule fires | `[{ triggered_at: "2025-01-15T08:00:00Z" }]` |
| HTTP response | `[{ department: "Electronics", revenue: 15000, order_count: 45 }, { department: "Clothing", revenue: 8200, order_count: 31 }, { department: "Books", revenue: 0, order_count: 0 }]` |
| After filter | Books removed (zero revenue) -- 2 departments remain |
| After aggregate | `[{ department: "Electronics", total_revenue: 15000, total_orders: 45, avg_order_value: 333.33 }, { department: "Clothing", total_revenue: 8200, total_orders: 31, avg_order_value: 264.52 }]` |
| Emails sent | One email per department to `leadership@company.com` |

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Schedule trigger (custom_cron) | `0 8 * * *` fires the workflow at 8 AM every day |
| workflow:schedule-run command | Must be registered in Laravel's scheduler to check due workflows |
| HTTP request with date expressions | `{{ date_format(now(), "Y-m-d") }}` injects today's date into the API URL |
| Filter utility | Removes zero-revenue departments to keep the report clean |
| Aggregate with group_by | Groups records by department and computes sum, avg across each group |
| Multiple aggregate operations | Three operations on the same group: sum revenue, sum orders, avg order value |
| Per-item email delivery | Each aggregated row becomes an item, so one email is sent per department |


</div>
