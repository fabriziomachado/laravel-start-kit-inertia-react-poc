# Stripe Webhook İşleyici

> [English](../05-webhook-stripe-integration.md) | Türkçe

Stripe webhook eventlerini al, event tipine göre yönlendir, veritabanındaki siparişi güncelle ve doğru e-postayı gönder. Bu örnek `webhook` tetikleyici, `switch` yönlendirme, `update_model` ve `delay` node'larını gösterir.

## Akış

```
[Webhook Tetikleyici] → [Switch: event tipi]
                            ├─ ödeme başarılı → [Model Güncelle: ödendi]    → [E-posta: makbuz]
                            ├─ ödeme başarısız → [Model Güncelle: başarısız] → [E-posta: yeniden deneme] → [Gecikme: 1sa] → [HTTP: tekrar tahsil]
                            └─ iade           → [Model Güncelle: iade edildi] → [E-posta: iade onayı]
```

## Adım 1 — Workflow'u Tanımla

Bir artisan komutu oluşturup `php artisan workflow:setup-stripe` ile bir kez çalıştırın.

```php
// app/Console/Commands/SetupStripeWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupStripeWorkflow extends Command
{
    protected $signature = 'workflow:setup-stripe';
    protected $description = 'Stripe webhook handler workflow\'unu oluştur';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Stripe Webhooks']);

        $trigger = $workflow->addNode('Stripe Webhook', 'webhook', [
            'method'    => 'POST',
            'auth_type' => 'header_key',
        ]);

        $switchEvent = $workflow->addNode('Route by Event', 'switch', [
            'field' => 'type',
            'cases' => [
                ['port' => 'case_succeeded', 'operator' => 'equals', 'value' => 'payment_intent.succeeded'],
                ['port' => 'case_failed',    'operator' => 'equals', 'value' => 'payment_intent.payment_failed'],
                ['port' => 'case_refund',    'operator' => 'equals', 'value' => 'charge.refunded'],
            ],
        ]);

        // ── Ödeme başarılı ────────────────────────────────────

        $markPaid = $workflow->addNode('Mark Paid', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_payment_intent',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => ['status' => 'paid', 'paid_at' => '{{ now() }}'],
        ]);

        $sendReceipt = $workflow->addNode('Send Receipt', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Payment Confirmed — Order #{{ item.data.object.metadata.order_id }}',
            'body'    => 'Your payment of ${{ item.data.object.amount / 100 }} has been confirmed.',
        ]);

        // ── Ödeme başarısız ───────────────────────────────────

        $markFailed = $workflow->addNode('Mark Failed', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_payment_intent',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => ['status' => 'payment_failed'],
        ]);

        $sendRetryNotice = $workflow->addNode('Retry Notice', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Payment Failed — Action Required',
            'body'    => 'Your payment could not be processed. We will retry in 1 hour.',
        ]);

        $delay = $workflow->addNode('Wait 1 Hour', 'delay', [
            'delay_seconds' => 3600, // 1 saat
        ]);

        $retryCharge = $workflow->addNode('Retry Charge', 'http_request', [
            'url'    => 'https://api.stripe.com/v1/payment_intents/{{ item.data.object.id }}/confirm',
            'method' => 'POST',
        ]);

        // ── İade ──────────────────────────────────────────────

        $markRefunded = $workflow->addNode('Mark Refunded', 'update_model', [
            'model'      => 'App\\Models\\Order',
            'find_by'    => 'stripe_charge_id',
            'find_value' => '{{ item.data.object.id }}',
            'fields'     => ['status' => 'refunded', 'refunded_at' => '{{ now() }}'],
        ]);

        $sendRefundEmail = $workflow->addNode('Refund Confirmation', 'send_mail', [
            'to'      => '{{ item.data.object.receipt_email }}',
            'subject' => 'Refund Processed',
            'body'    => 'Your refund of ${{ item.data.object.amount_refunded / 100 }} has been processed.',
        ]);

        // Edge'ler
        $trigger->connect($switchEvent);

        $switchEvent->connect($markPaid, sourcePort: 'case_succeeded');
        $markPaid->connect($sendReceipt);

        $switchEvent->connect($markFailed, sourcePort: 'case_failed');
        $markFailed->connect($sendRetryNotice);
        $sendRetryNotice->connect($delay);
        $delay->connect($retryCharge);

        $switchEvent->connect($markRefunded, sourcePort: 'case_refund');
        $markRefunded->connect($sendRefundEmail);

        $workflow->activate();

        $this->info("Stripe Webhooks workflow created (ID: {$workflow->id})");
    }
}
```

## Adım 2 — Webhook URL'ini Al

Komutu çalıştırdıktan sonra, `webhook` node'u benzersiz bir UUID yolu oluşturur:

```php
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;

$node = WorkflowNode::where('name', 'Stripe Webhook')->first();
$url = url("workflow-webhook/{$node->config['path']}");
// → https://yourapp.com/workflow-webhook/a1b2c3d4-e5f6-...
```

Stripe'ın webhook ayarlarını bu URL'e yönlendirin. Uygulamanızda kod yazmanıza gerek yok — paket gelen isteği alır, kimlik doğrulamasını yapar ve workflow'u çalıştırır.

## Ne Olur

**`payment_intent.succeeded`:**

1. **Switch** → `case_succeeded` eşleşir
2. **Model Güncelle** → `Order`'ı `stripe_payment_intent` ile bulur, `status: paid` ayarlar
3. **E-posta** → Müşteri makbuz alır

**`payment_intent.payment_failed`:**

1. **Switch** → `case_failed` eşleşir
2. **Model Güncelle** → `status: payment_failed` ayarlar
3. **E-posta** → Müşteri yeniden deneme bildirimi alır
4. **Gecikme** → Workflow 1 saat duraklar (kuyruk tabanlı, non-blocking)
5. **HTTP İsteği** → Stripe API üzerinden ödemeyi yeniden dener

**`charge.refunded`:**

1. **Switch** → `case_refund` eşleşir
2. **Model Güncelle** → `status: refunded` ayarlar
3. **E-posta** → Müşteri iade onayı alır

## Gösterilen Kavramlar

| Kavram | Nasıl |
|--------|-------|
| Webhook tetikleyici | Dış servis (Stripe) oluşturulan URL'e POST gönderir |
| Çok yönlü yönlendirme | `switch` event tipine göre farklı dallara yönlendirir |
| Veritabanı güncelleme | `update_model` Eloquent modelleri bulur ve günceller |
| Non-blocking gecikme | `delay` Laravel kuyruklarını kullanır — worker bekleme süresinde serbesttir |
| İç içe ifadeler | `{{ item.data.object.metadata.order_id }}` derin iç içe veriye erişir |
