<?php

declare(strict_types=1);

use App\Models\User;

it('redirects guests from users index to login', function (): void {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('forbids non-admin from accessing users index', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

it('shows users index for admin', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('users.index'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('users'));
});

it('forbids non-admin from starting impersonation', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->get(route('impersonate', $other->id))
        ->assertForbidden();
});

it('forbids starting another impersonation while already impersonating', function (): void {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('users.index'))
        ->get(route('impersonate', $otherAdmin->id))
        ->assertRedirect(route('users.index'));

    $this->assertAuthenticatedAs($otherAdmin);

    $this->get(route('impersonate', $member->id))->assertForbidden();
});

it('allows admin to impersonate another user and then leave', function (): void {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('users.index'))
        ->get(route('impersonate', $member->id))
        ->assertRedirect(route('users.index'));

    $this->assertAuthenticatedAs($member);

    $this->from(route('users.index'))
        ->get(route('impersonate.leave'))
        ->assertRedirect(route('users.index'));

    $this->assertAuthenticatedAs($admin);
});
