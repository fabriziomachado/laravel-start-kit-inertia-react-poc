<?php

declare(strict_types=1);

use App\Models\User;
use BenBjurstrom\Otpz\Mail\OtpzMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @return non-empty-string
 */
function otpzRelativeUrl(string $absoluteUrl): string
{
    $path = parse_url($absoluteUrl, PHP_URL_PATH);
    $query = parse_url($absoluteUrl, PHP_URL_QUERY);
    expect($path)->toBeString()->not->toBeEmpty();

    return $query !== null && $query !== '' ? $path.'?'.$query : $path;
}

function otpzApplyCookiesFromResponse(TestCase $case, TestResponse $response): void
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->isCleared()) {
            continue;
        }

        $case->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }
}

/**
 * @return non-empty-string
 */
function otpzSessionIdFromSignedRedirectLocation(string $absoluteUrl): string
{
    $query = parse_url($absoluteUrl, PHP_URL_QUERY);
    expect($query)->toBeString()->not->toBeEmpty();
    parse_str($query, $params);
    $sessionId = $params['sessionId'] ?? null;
    expect($sessionId)->toBeString()->not->toBeEmpty();

    return $sessionId;
}

it('renders otp email page', function (): void {
    $this->get(route('otpz.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/otpz-login'));
});

it('rejects unknown email', function (): void {
    Mail::fake();

    User::factory()->create(['email' => 'known@example.com']);

    $this->fromRoute('otpz.index')
        ->post(route('otpz.store'), [
            'email' => 'unknown@example.com',
            'remember' => false,
        ])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

it('sends otp and redirects for existing user', function (): void {
    Mail::fake();

    User::factory()->withoutTwoFactor()->create([
        'email' => 'otp@example.com',
    ]);

    $response = $this->fromRoute('otpz.index')
        ->post(route('otpz.store'), [
            'email' => 'otp@example.com',
            'remember' => false,
        ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/otpz/');

    Mail::assertSent(OtpzMail::class);
});

it('shows verify page with valid signed url', function (): void {
    Mail::fake();

    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'show@example.com',
    ]);

    $this->get(route('otpz.index'))->assertOk();

    $storeResponse = $this->fromRoute('otpz.index')
        ->post(route('otpz.store'), [
            'email' => $user->email,
            'remember' => false,
        ]);

    $storeResponse->assertRedirect();
    $showUrl = $storeResponse->headers->get('Location');
    expect($showUrl)->toBeString()->not->toBeEmpty();

    otpzApplyCookiesFromResponse($this, $storeResponse);

    $this->get(otpzRelativeUrl($showUrl))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/otpz-verify')
            ->where('email', $user->email)
            ->has('url'));
});

it('rejects show when signature is invalid', function (): void {
    $this->get('/otpz/'.fake()->uuid())
        ->assertForbidden();
});

it('authenticates with valid otp code', function (): void {
    Mail::fake();

    $user = User::factory()->withoutTwoFactor()->unverified()->create([
        'email' => 'verify@example.com',
    ]);

    $this->get(route('otpz.index'))->assertOk();

    $storeResponse = $this->fromRoute('otpz.index')
        ->post(route('otpz.store'), [
            'email' => $user->email,
            'remember' => false,
        ]);

    otpzApplyCookiesFromResponse($this, $storeResponse);

    $sessionId = otpzSessionIdFromSignedRedirectLocation((string) $storeResponse->headers->get('Location'));

    $code = null;
    Mail::assertSent(OtpzMail::class, function (OtpzMail $mail) use (&$code): bool {
        $reflection = new ReflectionClass($mail);
        $prop = $reflection->getProperty('code');
        $prop->setAccessible(true);
        $code = $prop->getValue($mail);

        return true;
    });

    expect($code)->toBeString()->not->toBeEmpty();

    $otp = $user->fresh()->otps()->latest()->first();
    expect($otp)->not->toBeNull();

    $verifyUrl = URL::temporarySignedRoute(
        'otpz.verify',
        now()->addMinutes(5),
        [
            'id' => $otp->id,
            'sessionId' => $sessionId,
        ],
    );

    $response = $this->post(otpzRelativeUrl($verifyUrl), [
        'code' => $code,
        'sessionId' => $sessionId,
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('dashboard');

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects invalid otp code', function (): void {
    Mail::fake();

    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'badcode@example.com',
    ]);

    $storeResponse = $this->fromRoute('otpz.index')
        ->post(route('otpz.store'), [
            'email' => $user->email,
            'remember' => false,
        ]);

    otpzApplyCookiesFromResponse($this, $storeResponse);

    $sessionId = otpzSessionIdFromSignedRedirectLocation((string) $storeResponse->headers->get('Location'));

    $otp = $user->fresh()->otps()->latest()->first();
    expect($otp)->not->toBeNull();

    $verifyUrl = URL::temporarySignedRoute(
        'otpz.verify',
        now()->addMinutes(5),
        [
            'id' => $otp->id,
            'sessionId' => $sessionId,
        ],
    );

    $this->post(otpzRelativeUrl($verifyUrl), [
        'code' => '0000000000',
        'sessionId' => $sessionId,
    ])->assertSessionHasErrors('code');
});
