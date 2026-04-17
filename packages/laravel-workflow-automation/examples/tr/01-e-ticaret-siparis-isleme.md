# E-Ticaret Sipariş İşleme

> [English](../01-ecommerce-order-processing.md) | Türkçe

Müşteri sipariş verdiğinde, yüksek değerli mi kontrol et, öyleyse VIP ekibini bilgilendir ve her ürün için envanter güncelle. Bu örnek dallanma (`if_condition`), döngü (`loop`) ve dış API çağrısı (`http_request`) gösterir.

## Akış

```
[Manuel Tetikleyici] → [IF: total > 500]
                           ├─ true  → [E-posta: VIP bildirimi] → [Döngü: ürünler] → [HTTP: stok güncelle]
                           └─ false → [Döngü: ürünler] → [HTTP: stok güncelle]
```

## Adım 1 — Workflow'u Tanımla

Bir artisan komutu oluşturup `php artisan workflow:setup-orders` ile bir kez çalıştırın.

```php
// app/Console/Commands/SetupOrderWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupOrderWorkflow extends Command
{
    protected $signature = 'workflow:setup-orders';
    protected $description = 'Sipariş işleme workflow\'unu oluştur';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Order Processing']);

        $trigger = $workflow->addNode('New Order', 'manual');

        $checkAmount = $workflow->addNode('High Value?', 'if_condition', [
            'field'    => 'total',
            'operator' => 'greater_than',
            'value'    => 500,
        ]);

        $notifyVip = $workflow->addNode('Notify VIP Team', 'send_mail', [
            'to'      => 'vip-team@store.com',
            'subject' => 'VIP Order #{{ item.order_id }} — ${{ item.total }}',
            'body'    => '{{ item.customer_name }} placed a ${{ item.total }} order.',
        ]);

        $loop = $workflow->addNode('Each Item', 'loop', [
            'source_field' => 'items',
        ]);

        $updateStock = $workflow->addNode('Update Stock', 'http_request', [
            'url'    => 'https://inventory.api/stock/decrement',
            'method' => 'POST',
            'body'   => [
                'sku'      => '{{ item._loop_item.sku }}',
                'quantity' => '{{ item._loop_item.quantity }}',
            ],
        ]);

        // Edge'ler
        $trigger->connect($checkAmount);
        $checkAmount->connect($notifyVip, sourcePort: 'true');
        $notifyVip->connect($loop);
        $checkAmount->connect($loop, sourcePort: 'false');
        $loop->connect($updateStock, sourcePort: 'loop_item');

        $workflow->activate();

        $this->info("Order Processing workflow created (ID: {$workflow->id})");
    }
}
```

Her iki dal da aynı `$loop` node'una birleşir — VIP siparişler önce e-posta alır, sonra her iki yol da stok günceller. Node'ları çoğaltmaya gerek yok.

## Adım 2 — Controller'dan Tetikle

```php
// app/Http/Controllers/OrderController.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());

        $workflow = Workflow::where('name', 'Order Processing')->firstOrFail();

        $workflow->start([[
            'order_id'      => $order->id,
            'customer_name' => $order->customer_name,
            'total'         => $order->total,
            'items'         => $order->items->map(fn ($i) => [
                'sku'      => $i->sku,
                'quantity' => $i->quantity,
            ])->toArray(),
        ]]);

        return response()->json(['order' => $order], 201);
    }
}
```

## Ne Olur

`total: 750` ve 2 ürünlü bir sipariş verildiğinde:

1. **IF Koşulu** — `750 > 500` = true → VIP yolu
2. **E-posta** — VIP ekibi bilgilendirilir
3. **Döngü** — `items` dizisi üzerinde iterasyon (2 ürün)
4. **HTTP İsteği** — Envanter API'si ürün başına bir kez, toplam 2 kez çağrılır

Sipariş $200 olsaydı, e-postayı atlayıp doğrudan döngüye giderdi.

## Gösterilen Kavramlar

| Kavram | Nasıl |
|--------|-------|
| Dallanma | `if_condition` ile `sourcePort: 'true'` / `'false'` |
| Dalların birleşmesi | Her iki yol aynı `$loop` node'una bağlanır |
| Döngü | `loop` node'u diziyi genişletir, her birini `loop_item` portundan işler |
| Dış API çağrıları | İfade tabanlı body ile `http_request` |
