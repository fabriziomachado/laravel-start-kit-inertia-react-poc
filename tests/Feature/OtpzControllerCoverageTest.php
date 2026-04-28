<?php

declare(strict_types=1);

use BenBjurstrom\Otpz\Models\Otp;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;

it('store retorna throttle quando excede rate limit', function (): void {
    $email = 'ana@example.com';
    $ip = '127.0.0.1';

    // Reproduz throttleKey(): transliterate(lower(email)|ip)
    $key = \Illuminate\Support\Str::transliterate(mb_strtolower($email).'|'.$ip);

    for ($i = 0; $i < 6; $i++) {
        RateLimiter::hit($key, 300);
    }

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('otpz.index'))
        ->post(route('otpz.store'), ['email' => $email, 'remember' => false])
        ->assertSessionHasErrors(['email']);
});

it('store retorna erro quando otpz throttle dispara (OtpThrottleException)', function (): void {
    Mail::fake();

    config()->set('otpz.limits', [
        ['limit' => 0, 'minutes' => 1],
    ]);

    $user = \App\Models\User::factory()->create(['email' => 'ana.throttle@example.com']);

    $this->from(route('otpz.index'))
        ->post(route('otpz.store'), [
            'email' => $user->email,
            'remember' => false,
        ])
        ->assertSessionHasErrors(['email']);
});

it('show redireciona quando sessionId não confere', function (): void {
    $user = \App\Models\User::factory()->create();
    $otp = Otp::query()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'code' => '123456',
        'attempts' => 0,
        'status' => 0,
        'remember' => false,
        'ip_address' => '127.0.0.1',
    ]);

    $signed = URL::temporarySignedRoute('otpz.show', now()->addMinutes(5), [
        'id' => $otp->id,
        'sessionId' => 'outra-session',
    ]);

    $this->get($signed)
        ->assertRedirect(route('otpz.index'));
});

it('show redireciona quando assinatura é inválida', function (): void {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class)
        ->get(route('otpz.show', ['id' => 'x', 'sessionId' => 'y']))
        ->assertRedirect(route('otpz.index'))
        ->assertSessionHas('status');
});

