# Data Pipeline (ETL)

> English | **[Türkçe](tr/03-veri-hatti-etl.md)**

Fetch sales data from an external API, filter out incomplete records, calculate net revenue, and aggregate totals by region. This example shows how to build an ETL pipeline using `http_request`, `filter`, `code`, and `aggregate` nodes.

## Flow

```
[Manual Trigger] → [HTTP: fetch sales] → [Filter: completed only] → [Code: net revenue] → [Aggregate: by region] → [HTTP: push report]
```

## Step 1 — Define the Workflow

Create an artisan command and run it once with `php artisan workflow:setup-sales-pipeline`.

```php
// app/Console/Commands/SetupSalesPipeline.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupSalesPipeline extends Command
{
    protected $signature = 'workflow:setup-sales-pipeline';
    protected $description = 'Create the sales data pipeline workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Sales Pipeline']);

        $trigger = $workflow->addNode('Start', 'manual');

        $fetchData = $workflow->addNode('Fetch Sales', 'http_request', [
            'url'    => 'https://sales-api.example.com/transactions?date={{ payload.date }}',
            'method' => 'GET',
        ]);

        $filterCompleted = $workflow->addNode('Completed Only', 'filter', [
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'completed'],
                ['field' => 'amount', 'operator' => 'greater_than', 'value' => 0],
            ],
            'logic' => 'and',
        ]);

        $calcRevenue = $workflow->addNode('Net Revenue', 'code', [
            'mode'       => 'transform',
            'expression' => '{{ item.amount * (1 - item.discount / 100) }}',
        ]);

        $aggregate = $workflow->addNode('By Region', 'aggregate', [
            'group_by'   => 'region',
            'operations' => [
                ['field' => '_result', 'function' => 'sum',   'alias' => 'total_revenue'],
                ['field' => '_result', 'function' => 'count', 'alias' => 'transaction_count'],
            ],
        ]);

        $pushReport = $workflow->addNode('Push Report', 'http_request', [
            'url'    => 'https://reports.example.com/ingest',
            'method' => 'POST',
            'body'   => ['report_type' => 'daily_sales', 'date' => '{{ payload.date }}'],
        ]);

        // Edges — a straight pipeline
        $trigger->connect($fetchData);
        $fetchData->connect($filterCompleted);
        $filterCompleted->connect($calcRevenue);
        $calcRevenue->connect($aggregate);
        $aggregate->connect($pushReport);

        $workflow->activate();

        $this->info("Sales Pipeline workflow created (ID: {$workflow->id})");
    }
}
```

## Step 2 — Trigger It

From a controller, another command, or anywhere:

```php
$workflow = Workflow::where('name', 'Sales Pipeline')->firstOrFail();
$workflow->start([['date' => '2025-03-01']]);
```

Or schedule it to run daily:

```php
// routes/console.php
Schedule::command('pipeline:sales')->dailyAt('06:00');
```

## Example Data Flow

**API returns 4 transactions:**

| id | region | amount | discount | status |
|----|--------|--------|----------|--------|
| 1 | US | 100 | 10 | completed |
| 2 | EU | 200 | 0 | completed |
| 3 | US | 50 | 0 | refunded |
| 4 | US | 150 | 20 | completed |

**After Filter** — removes tx #3 (refunded):

3 transactions remain.

**After Code** — calculates net revenue:

| id | region | net revenue |
|----|--------|-------------|
| 1 | US | $90 (100 × 0.9) |
| 2 | EU | $200 (200 × 1.0) |
| 4 | US | $120 (150 × 0.8) |

**After Aggregate** — grouped by region:

```json
[
    {"region": "US", "total_revenue": 210, "transaction_count": 2},
    {"region": "EU", "total_revenue": 200, "transaction_count": 1}
]
```

## Concepts Demonstrated

| Concept | How |
|---------|-----|
| Linear pipeline | Nodes connected in a straight chain — no branching |
| Filtering | `filter` removes records that don't match conditions |
| Expression-based transform | `code` node calculates values without PHP eval |
| Aggregation | `aggregate` groups items and applies sum/count |
| Payload access | `{{ payload.date }}` reads the original trigger data |
