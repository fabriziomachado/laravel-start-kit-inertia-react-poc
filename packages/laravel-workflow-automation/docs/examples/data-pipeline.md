<div v-pre>

# Data Transformation Pipeline

Receive raw CSV data via webhook, parse it, normalize fields, filter for active records, sync each record to an external API, aggregate the results, and email a summary report. This showcases the full transformer and utility node toolkit.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - webhook         Receive CSV data via POST         |
|  - parse_data      Parse CSV into structured rows    |
|  - set_fields      Add metadata and normalize        |
|  - filter          Keep only active records          |
|  - loop            Process each record individually  |
|  - http_request    Sync records to external CRM      |
|  - aggregate       Count total synced records        |
|  - send_mail       Email summary report              |
|                                                      |
|  Concepts: webhook trigger, CSV parsing, field       |
|  transformation, filtering, loop with loop_done,     |
|  _loop_parent references, aggregation, 8-node        |
|  pipeline                                            |
+------------------------------------------------------+
```

## Workflow Diagram

```text
    +------------------+     +------------------+     +------------------+
    |  Webhook:        | --> |  Parse Data:     | --> |  Set Fields:     |
    |  receive CSV     |     |  CSV format      |     |  normalize       |
    +------------------+     +------------------+     +------------------+
                                                              |
                                                              v
    +------------------+     +------------------+     +------------------+
    |  Email:          | <-- |  Aggregate:      | <-- |  Filter:         |
    |  summary report  |     |  count results   |     |  active only     |
    +------------------+     +------------------+     +------------------+
                                      ^
                                      |  loop_done
                             +--------+----------+
                             |  Loop:            |
                             |  each record      |
                             +--------+----------+
                                      | loop_item
                                      v
                             +--------+----------+
                             |  HTTP POST:       |
                             |  sync to CRM      |
                             +-------------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupDataPipeline.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupDataPipeline extends Command
{
    protected $signature = 'workflow:setup-data-pipeline';
    protected $description = 'Create the CSV data transformation pipeline';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Data Transformation Pipeline']);

        // 1. Webhook receives the raw CSV payload
        $trigger = $workflow->addNode('Receive CSV', 'webhook', [
            'method'     => 'POST',
            'auth_type'  => 'bearer',
            'auth_value' => 'pipeline-secret-token',
        ]);

        // 2. Parse the CSV string into structured rows
        $parse = $workflow->addNode('Parse CSV', 'parse_data', [
            'source_field' => 'csv_body',
            'format'       => 'csv',
            'target_field' => 'records',
        ]);

        // 3. Normalize fields — lowercase emails, add a processed timestamp
        $normalize = $workflow->addNode('Normalize Data', 'set_fields', [
            'fields' => [
                'records'      => '{{ item.records }}',
                'processed_at' => '{{ now() }}',
                'source'       => 'csv_import',
            ],
            'keep_existing' => true,
        ]);

        // 4. Filter to keep only active records
        $filter = $workflow->addNode('Active Only', 'filter', [
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
            'logic' => 'and',
        ]);

        // 5. Loop through each active record
        $loop = $workflow->addNode('Each Record', 'loop', [
            'source_field' => 'records',
        ]);

        // 6. Sync each record to the external CRM API
        $sync = $workflow->addNode('Sync to CRM', 'http_request', [
            'url'    => 'https://crm.example.com/api/contacts/upsert',
            'method' => 'POST',
            'body'   => [
                'email'  => '{{ item._loop_item.email }}',
                'name'   => '{{ item._loop_item.name }}',
                'phone'  => '{{ item._loop_item.phone }}',
                'source' => '{{ item._loop_parent.source }}',
            ],
            'headers' => [
                'Authorization' => 'Bearer {{ env.CRM_API_TOKEN }}',
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
        ]);

        // 7. Aggregate sync results into summary counts
        $aggregate = $workflow->addNode('Summarize', 'aggregate', [
            'operations' => [
                ['field' => 'email', 'function' => 'count', 'alias' => 'total_synced'],
            ],
        ]);

        // 8. Email the summary report
        $report = $workflow->addNode('Send Report', 'send_mail', [
            'to'      => 'data-team@company.com',
            'subject' => 'CSV Import Complete — {{ item.total_synced }} records synced',
            'body'    => 'The CSV data pipeline finished at {{ now() }}. Total records synced: {{ item.total_synced }}.',
            'is_html' => false,
        ]);

        // Wire the graph
        $trigger->connect($parse);
        $parse->connect($normalize);
        $normalize->connect($filter);
        $filter->connect($loop);
        $loop->connect($sync, 'loop_item');
        $loop->connect($aggregate, 'loop_done');
        $aggregate->connect($report);

        $workflow->activate();

        $this->info("Data pipeline created (ID: {$workflow->id})");
    }
}
```

## Triggering the Workflow

Send a POST request to the webhook URL with CSV content in the body:

```bash
curl -X POST https://yourapp.com/workflow-webhook/{uuid} \
  -H "Authorization: Bearer pipeline-secret-token" \
  -H "Content-Type: application/json" \
  -d '{
    "csv_body": "name,email,phone,status\nAlice,alice@example.com,555-0101,active\nBob,bob@example.com,555-0102,inactive\nCharlie,charlie@example.com,555-0103,active"
  }'
```

## What Happens

1. **Webhook** receives the JSON payload containing the `csv_body` field with raw CSV text.
2. **Parse Data** reads `csv_body`, parses it as CSV using the first row as headers, and stores an array of row objects in `records`.
3. **Set Fields** keeps existing data and adds `processed_at` (current timestamp) and `source` (`"csv_import"`) to each item.
4. **Filter** removes records where `status` is not `"active"`. Bob (inactive) is dropped; Alice and Charlie pass through.
5. **Loop** iterates over the `records` array. Each record becomes a separate item with `_loop_item` containing `name`, `email`, and `phone`.
6. **HTTP POST** fires once per active record, upserting the contact in the CRM. The `_loop_parent.source` expression pulls data from the parent item.
7. **Aggregate** counts the total synced records after the loop completes (via the `loop_done` port).
8. **Send Mail** emails the data team with the sync count.

### Example Data Flow

| Stage | Data Shape |
|-------|-----------|
| Webhook receives | `{ csv_body: "name,email..." }` |
| After Parse | `{ records: [{name: "Alice", ...}, {name: "Bob", ...}, ...] }` |
| After Filter | 2 of 3 records remain (active only) |
| Loop emits | Individual items: `{ _loop_item: {name: "Alice", ...} }` |
| After Aggregate | `{ total_synced: 2 }` |
| Email sent | Subject: "CSV Import Complete -- 2 records synced" |

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Webhook trigger with auth | Bearer token secures the endpoint from unauthorized callers |
| Parse Data node (CSV) | Converts raw CSV text into a structured array of objects |
| Set Fields transformer | Adds metadata (`processed_at`, `source`) to the item |
| Filter utility | Removes inactive records before processing |
| Loop with loop_done port | `loop_item` processes each record; `loop_done` fires once for aggregation |
| Loop parent references | `{{ item._loop_parent.source }}` accesses the parent item inside the loop |
| Aggregate utility | Counts records after per-element processing completes |
| Multi-node pipeline | Eight nodes chained in sequence with a loop branch |


</div>
