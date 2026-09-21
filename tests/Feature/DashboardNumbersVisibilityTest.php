<?php

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\DashboardNumbersVisibility;

test('dashboard numbers follow the general settings default when the user has no preference', function () {
    $user = User::factory()->create();

    expect(DashboardNumbersVisibility::for($user))->toBeTrue();

    SystemSetting::current()->update(['show_dashboard_numbers' => false]);

    expect(DashboardNumbersVisibility::for($user->refresh()))->toBeFalse();
});

test('user dashboard numbers preference overrides the general settings default', function () {
    $user = User::factory()->create([
        'show_dashboard_numbers' => false,
    ]);

    SystemSetting::current()->update(['show_dashboard_numbers' => true]);

    expect(DashboardNumbersVisibility::for($user))->toBeFalse();

    $user->update(['show_dashboard_numbers' => true]);
    SystemSetting::current()->update(['show_dashboard_numbers' => false]);

    expect(DashboardNumbersVisibility::for($user->refresh()))->toBeTrue();
});

test('user can persist dashboard numbers visibility from the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('dashboard.numbers-visibility'), [
            'show_dashboard_numbers' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->show_dashboard_numbers)->toBeFalse()
        ->and(DashboardNumbersVisibility::for($user))->toBeFalse();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('dashboard.numbers-visibility'), [
            'show_dashboard_numbers' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->show_dashboard_numbers)->toBeTrue();
});

test('dashboard numbers preference is stored per user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    SystemSetting::current()->update(['show_dashboard_numbers' => true]);

    $this->actingAs($user)
        ->patch(route('dashboard.numbers-visibility'), [
            'show_dashboard_numbers' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->show_dashboard_numbers)->toBeFalse()
        ->and($other->refresh()->show_dashboard_numbers)->toBeNull()
        ->and(DashboardNumbersVisibility::for($other))->toBeTrue();
});

test('changing the general settings default does not override a stored user preference', function () {
    $admin = User::factory()->administrator()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.numbers-visibility'), [
            'show_dashboard_numbers' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->put(route('settings.general.update'), [
            'app_name' => 'SDMS',
            'currency' => 'usd',
            'show_dashboard_numbers' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->show_dashboard_numbers)->toBeTrue()
        ->and(DashboardNumbersVisibility::for($user->refresh()))->toBeTrue()
        ->and(SystemSetting::current()->show_dashboard_numbers)->toBeFalse();
});

test('guests cannot update dashboard numbers visibility', function () {
    $this->patch(route('dashboard.numbers-visibility'), [
        'show_dashboard_numbers' => false,
    ])->assertRedirect(route('login'));
});

test('dashboard numbers visibility requires a boolean', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('dashboard.numbers-visibility'), [])
        ->assertSessionHasErrors('show_dashboard_numbers')
        ->assertRedirect(route('dashboard'));
});

test('roles with dashboard access can update their numbers visibility', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->patch(route('dashboard.numbers-visibility'), [
            'show_dashboard_numbers' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->show_dashboard_numbers)->toBeFalse();
})->with(UserRole::cases());
