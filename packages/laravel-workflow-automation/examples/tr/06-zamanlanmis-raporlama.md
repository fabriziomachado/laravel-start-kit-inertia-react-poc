# Zamanlanmış Günlük Rapor

> [English](../06-scheduled-reporting.md) | Türkçe

Her sabah saat 8'de dünkü satış verilerini çek, sıfır gelirli kayıtları filtrele, departmana göre topla ve özeti e-postayla gönder. Bu örnek `schedule` tetikleyicisini ve doğrusal veri işleme pipeline'ını gösterir.

## Akış

```
[Zamanlama: günlük 8:00] → [HTTP: satış çek] → [Filtre: sıfır olmayan] → [Toplama: departmana göre] → [E-posta: rapor]
```

## Adım 1 — Workflow'u Tanımla

Bir artisan komutu oluşturup `php artisan workflow:setup-daily-report` ile bir kez çalıştırın.

```php
// app/Console/Commands/SetupDailyReport.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupDailyReport extends Command
{
    protected $signature = 'workflow:setup-daily-report';
    protected $description = 'Günlük satış raporu workflow\'unu oluştur';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Daily Sales Report']);

        $trigger = $workflow->addNode('Daily 8 AM', 'schedule', [
            'interval_type' => 'custom_cron',
            'cron'          => '0 8 * * *', // Her gün saat 8:00'de
        ]);

        $fetchData = $workflow->addNode('Fetch Sales', 'http_request', [
            'url'    => 'https://analytics.example.com/api/daily-sales?date={{ date_format(now(), "Y-m-d") }}',
            'method' => 'GET',
        ]);

        $filterNonZero = $workflow->addNode('Non-Zero Revenue', 'filter', [
            'conditions' => [
                ['field' => 'revenue', 'operator' => 'greater_than', 'value' => 0],
            ],
        ]);

        $aggregate = $workflow->addNode('By Department', 'aggregate', [
            'group_by'   => 'department',
            'operations' => [
                ['field' => 'revenue',      'function' => 'sum', 'alias' => 'total_revenue'],
                ['field' => 'transactions', 'function' => 'sum', 'alias' => 'total_transactions'],
            ],
        ]);

        $sendReport = $workflow->addNode('Email Report', 'send_mail', [
            'to'      => 'team@company.com',
            'subject' => 'Daily Sales Report — {{ date_format(now(), "M d, Y") }}',
            'body'    => 'Günlük satış raporu ektedir.',
        ]);

        // Edge'ler
        $trigger->connect($fetchData);
        $fetchData->connect($filterNonZero);
        $filterNonZero->connect($aggregate);
        $aggregate->connect($sendReport);

        $workflow->activate();

        $this->info("Daily Sales Report workflow created (ID: {$workflow->id})");
    }
}
```

## Adım 2 — Zamanlama Çalıştırıcısını Etkinleştir

Paket, her dakika tüm zamanlama tetikleyicilerini kontrol eden `workflow:schedule-run` komutu sağlar. Laravel zamanlayıcınıza ekleyin:

```php
// routes/console.php
Schedule::command('workflow:schedule-run')->everyMinute();
```

Laravel zamanlayıcısının kendisinin çalıştığından emin olun:

```bash
* * * * * cd /proje-yolu && php artisan schedule:run >> /dev/null 2>&1
```

Bu kadar. Her gün saat 8:00'de workflow otomatik çalışır.

## Örnek Veri Akışı

**API şunları döner:**

| departman | gelir | işlem sayısı |
|-----------|-------|--------------|
| Elektronik | 15000 | 42 |
| Giyim | 8500 | 67 |
| Kitap | 0 | 0 |
| Elektronik | 3200 | 15 |

**Filtre sonrası** — Kitap kaldırılır (sıfır gelir).

**Toplama sonrası** — departmana göre gruplanır:

```json
[
    {"department": "Elektronik", "total_revenue": 18200, "total_transactions": 57},
    {"department": "Giyim",      "total_revenue": 8500,  "total_transactions": 67}
]
```

## Diğer Zamanlama Seçenekleri

```php
// Her 5 dakikada bir
$workflow->addNode('Every 5 Min', 'schedule', [
    'interval_type'  => 'minutes',
    'interval_value' => 5,
]);

// Hafta içi saat 9'da
$workflow->addNode('Weekday 9 AM', 'schedule', [
    'interval_type' => 'custom_cron',
    'cron'          => '0 9 * * 1-5',
]);

// Her ayın ilk günü
$workflow->addNode('Monthly', 'schedule', [
    'interval_type' => 'custom_cron',
    'cron'          => '0 0 1 * *',
]);
```

## Gösterilen Kavramlar

| Kavram | Nasıl |
|--------|-------|
| Cron tabanlı tetikleyici | `schedule` ile `custom_cron` belirli bir saatte çalışır |
| Manuel tetikleme yok | `workflow:schedule-run` otomatik dispatch eder |
| Veri filtreleme | `filter` sıfır gelirli kayıtları kaldırır |
| Toplama | `aggregate` departmana göre gruplar ve toplar |
| Yerleşik fonksiyonlar | İfadelerde `{{ date_format(now(), "Y-m-d") }}` |
