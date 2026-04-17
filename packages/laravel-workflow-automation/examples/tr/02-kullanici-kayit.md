# Kullanıcı Kayıt Akışı

> [English](../02-user-onboarding.md) | Türkçe

Yeni kullanıcı kayıt olduğunda, nasıl kaydolduğuna göre (organik, referans veya reklam kampanyası) farklı bir hoş geldin e-postası gönder. Referansla geldiyse, referans vereni de ödüllendir. Bu örnek otomatik model-event tetikleme ve `switch` ile çok yönlü dallanma gösterir.

## Akış

```
[Model Event: User created] → [Switch: kaynak]
                                  ├─ case_organic  → [E-posta: organik hoş geldin]
                                  ├─ case_referral → [E-posta: referans hoş geldin] → [HTTP: referans ödülü]
                                  └─ default       → [E-posta: genel hoş geldin]
```

## Adım 1 — Workflow'u Tanımla

Bir artisan komutu oluşturup `php artisan workflow:setup-onboarding` ile bir kez çalıştırın.

```php
// app/Console/Commands/SetupOnboardingWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupOnboardingWorkflow extends Command
{
    protected $signature = 'workflow:setup-onboarding';
    protected $description = 'Kullanıcı kayıt workflow\'unu oluştur';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'User Onboarding']);

        $trigger = $workflow->addNode('User Created', 'model_event', [
            'model'  => 'App\\Models\\User',
            'events' => ['created'],
        ]);

        $switchSource = $workflow->addNode('Check Source', 'switch', [
            'field' => 'source',
            'cases' => [
                ['port' => 'case_organic',  'operator' => 'equals', 'value' => 'organic'],
                ['port' => 'case_referral', 'operator' => 'equals', 'value' => 'referral'],
            ],
        ]);

        $welcomeOrganic = $workflow->addNode('Welcome (Organic)', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Welcome, {{ item.name }}!',
            'body'    => 'Thanks for signing up. Start your 14-day trial today.',
        ]);

        $welcomeReferral = $workflow->addNode('Welcome (Referral)', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Your friend invited you! Welcome, {{ item.name }}',
            'body'    => 'You were referred by a friend — both of you get bonus credits.',
        ]);

        $creditReferrer = $workflow->addNode('Credit Referrer', 'http_request', [
            'url'    => 'https://api.yourapp.com/referrals/credit',
            'method' => 'POST',
            'body'   => [
                'referrer_code' => '{{ item.referral_code }}',
                'new_user_id'   => '{{ item.id }}',
            ],
        ]);

        $welcomeGeneric = $workflow->addNode('Welcome (Generic)', 'send_mail', [
            'to'      => '{{ item.email }}',
            'subject' => 'Welcome, {{ item.name }}!',
            'body'    => 'We are glad to have you.',
        ]);

        // Edge'ler
        $trigger->connect($switchSource);
        $switchSource->connect($welcomeOrganic, sourcePort: 'case_organic');
        $switchSource->connect($welcomeReferral, sourcePort: 'case_referral');
        $welcomeReferral->connect($creditReferrer);
        $switchSource->connect($welcomeGeneric, sourcePort: 'default');

        $workflow->activate();

        $this->info("User Onboarding workflow created (ID: {$workflow->id})");
    }
}
```

## Adım 2 — Model Event Listener'ı Kaydet

`AppServiceProvider`'ınıza tek satır ekleyin. Bu, pakete Eloquent eventlerini izlemesini söyler:

```php
// app/Providers/AppServiceProvider.php

use Aftandilmmd\WorkflowAutomation\Listeners\ModelEventListener;

public function boot(): void
{
    ModelEventListener::register();
}
```

## Adım 3 — Kendiliğinden Çalışır

`Workflow::run()` gerekmez. Kullanıcı kayıt olduğunda workflow otomatik tetiklenir:

```php
// Uygulamanızın herhangi bir yerinde
User::create([
    'name'          => 'Alice',
    'email'         => 'alice@example.com',
    'password'      => bcrypt('secret'),
    'source'        => 'referral',
    'referral_code' => 'BOB123',
]);
// → Workflow çalışır: referans hoş geldin e-postası + referans API çağrısı
```

## Ne Olur

`User::create(['source' => 'referral', ...])` çağrıldığında:

1. **Model Event** — `User::created` üzerinde tetiklenir
2. **Switch** — `source` alanını kontrol eder → `case_referral` eşleşir
3. **E-posta** — Referans hoş geldin e-postası kullanıcıya gönderilir
4. **HTTP İsteği** — Referans API'si çağrılarak referans veren ödüllendirilir

`source = 'organic'` ise → organik hoş geldin. `source = 'google_ads'` ise → hiçbir case eşleşmez → `default` portu → genel hoş geldin.

## Gösterilen Kavramlar

| Kavram | Nasıl |
|--------|-------|
| Otomatik tetikleme | `model_event` — `User::created`'da tetiklenir, manuel çağrı gerekmez |
| Çok yönlü dallanma | İsimli portlarla `switch` (`case_organic`, `case_referral`, `default`) |
| Sıralı aksiyonlar | Referans hoş geldin → referans ödülü (sırayla bağlı) |
| Yedek yönlendirme | Eşleşmeyen case'ler `default` portuna gider |
