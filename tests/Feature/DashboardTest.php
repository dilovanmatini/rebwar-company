<?php

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard lists section shortcuts instead of metrics', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->missing('metrics')
            ->missing('show_dashboard_numbers')
            ->has('groups', 3)
            ->where('groups.0.key', 'sales')
            ->has('groups.0.shortcuts', 4)
            ->where('groups.0.shortcuts.0.key', 'sales-invoices')
            ->where('groups.0.shortcuts.0.href', route('sales-invoices.index'))
            ->where('groups.0.shortcuts.1.key', 'payment-receipts')
            ->where('groups.0.shortcuts.1.href', route('payment-receipts.index'))
            ->where('groups.0.shortcuts.2.key', 'distributors')
            ->where('groups.0.shortcuts.2.href', route('distributors.index'))
            ->where('groups.0.shortcuts.3.key', 'statements')
            ->where('groups.0.shortcuts.3.href', route('statements.index'))
            ->where('groups.1.key', 'purchasing')
            ->has('groups.1.shortcuts', 4)
            ->where('groups.1.shortcuts.0.key', 'purchases')
            ->where('groups.1.shortcuts.0.href', route('purchases.index'))
            ->where('groups.1.shortcuts.1.key', 'inventory')
            ->where('groups.1.shortcuts.1.href', route('inventory.index'))
            ->where('groups.1.shortcuts.2.key', 'suppliers')
            ->where('groups.1.shortcuts.2.href', route('suppliers.index'))
            ->where('groups.1.shortcuts.3.key', 'products')
            ->where('groups.1.shortcuts.3.href', route('products.index'))
            ->where('groups.2.key', 'system')
            ->has('groups.2.shortcuts', 2)
            ->where('groups.2.shortcuts.0.key', 'reports')
            ->where('groups.2.shortcuts.0.href', route('reports.index'))
            ->where('groups.2.shortcuts.1.key', 'settings')
            ->where('groups.2.shortcuts.1.href', route('settings.index')));
});

test('dashboard hides shortcuts the user cannot access', function () {
    $user = User::factory()->create(['role' => UserRole::Warehouse]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('groups', 2)
            ->where('groups.0.key', 'purchasing')
            ->has('groups.0.shortcuts', 4)
            ->where('groups.1.key', 'system')
            ->has('groups.1.shortcuts', 1)
            ->where('groups.1.shortcuts.0.key', 'settings')
            ->where('groups', fn ($groups) => collect($groups)
                ->flatMap(fn ($group) => $group['shortcuts'])
                ->contains('key', 'sales-invoices') === false)
            ->where('groups', fn ($groups) => collect($groups)
                ->flatMap(fn ($group) => $group['shortcuts'])
                ->contains('key', 'reports') === false));
});
