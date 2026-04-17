# Veri Hattı (ETL)

> [English](../03-data-pipeline-etl.md) | Türkçe

Dış API'den satış verilerini çek, eksik kayıtları filtrele, net geliri hesapla ve bölgeye göre toplamları al. Bu örnek `http_request`, `filter`, `code` ve `aggregate` node'larıyla ETL pipeline nasıl kurulur gösterir.

## Akış

```
[Manuel Tetikleyici] → [HTTP: satış çek] → [Filtre: tamamlananlar] → [Kod: net gelir] → [Toplama: bölgeye göre] → [HTTP: rapor gönder]
```

## Adım 1 — Workflow'u Tanımla

Bir artisan komutu oluşturup `php artisan workflow:setup-sales-pipeline` ile bir kez çalıştırın.

```php
// app/Console/Commands/SetupSalesPipeline.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupSalesPipeline extends Command
{
    protected $signature = 'workflow:setup-sales-pipeline';
    protected $description = 'Satış veri hattı workflow\'unu oluştur';

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

        // Edge'ler — düz bir pipeline
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

## Adım 2 — Tetikle

Controller, başka bir komut veya herhangi bir yerden:

```php
$workflow = Workflow::where('name', 'Sales Pipeline')->firstOrFail();
$workflow->start([['date' => '2025-03-01']]);
```

Veya günlük çalışacak şekilde zamanlayın:

```php
// routes/console.php
Schedule::command('pipeline:sales')->dailyAt('06:00');
```

## Örnek Veri Akışı

**API 4 işlem döner:**

| id | bölge | tutar | indirim | durum |
|----|-------|-------|---------|-------|
| 1 | US | 100 | 10 | completed |
| 2 | EU | 200 | 0 | completed |
| 3 | US | 50 | 0 | refunded |
| 4 | US | 150 | 20 | completed |

**Filtre sonrası** — tx #3 (iade) kaldırılır:

3 işlem kalır.

**Kod sonrası** — net gelir hesaplanır:

| id | bölge | net gelir |
|----|-------|-----------|
| 1 | US | $90 (100 × 0.9) |
| 2 | EU | $200 (200 × 1.0) |
| 4 | US | $120 (150 × 0.8) |

**Toplama sonrası** — bölgeye göre gruplanır:

```json
[
    {"region": "US", "total_revenue": 210, "transaction_count": 2},
    {"region": "EU", "total_revenue": 200, "transaction_count": 1}
]
```

## Gösterilen Kavramlar

| Kavram | Nasıl |
|--------|-------|
| Doğrusal pipeline | Node'lar düz bir zincirde bağlı — dallanma yok |
| Filtreleme | `filter` koşullara uymayan kayıtları kaldırır |
| İfade tabanlı dönüşüm | `code` node'u PHP eval olmadan değer hesaplar |
| Toplama | `aggregate` öğeleri gruplar ve sum/count uygular |
| Payload erişimi | `{{ payload.date }}` orijinal tetikleyici verisini okur |
