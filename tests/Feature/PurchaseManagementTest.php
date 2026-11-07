<?php

use App\Enums\DocumentStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\UserRole;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('purchase create-edit shows quantities without trailing zeros', function () {
    $admin = User::factory()->administrator()->create();
    $purchase = Purchase::factory()
        ->for(Supplier::factory())
        ->create(['status' => DocumentStatus::Draft]);

    PurchaseLine::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 5,
    ]);

    $this->actingAs($admin)
        ->get(route('purchases.create-edit', $purchase))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/create-edit')
            ->where('purchase.lines.0.quantity', '5'));
});

test('administrator can create a draft purchase with lines', function () {
    $admin = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('purchases.store-update'), [
            'purchase_date' => '2026-08-07',
            'receipt_number' => '  وصل-8841  ',
            'supplier_id' => $supplier->id,
            'notes' => 'اختبار',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 12.5],
            ],
        ])
        ->assertRedirect(route('purchases.create-edit', Purchase::query()->first()));

    $purchase = Purchase::query()->first();

    expect($purchase)->not->toBeNull()
        ->and($purchase->number)->toStartWith('PUR-')
        ->and($purchase->receipt_number)->toBe('وصل-8841')
        ->and($purchase->status)->toBe(DocumentStatus::Draft)
        ->and($purchase->lines)->toHaveCount(1)
        ->and((string) $purchase->lines->first()->quantity)->toBe('12.500');
});

test('posting a purchase creates inventory transactions and increases stock', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create();
    $purchase = Purchase::factory()
        ->for(Supplier::factory())
        ->create(['status' => DocumentStatus::Draft]);

    PurchaseLine::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 40,
    ]);

    $this->actingAs($admin)
        ->post(route('purchases.post', $purchase))
        ->assertRedirect(route('purchases.index'));

    $purchase->refresh();

    expect($purchase->status)->toBe(DocumentStatus::Posted)
        ->and($purchase->posted_by)->toBe($admin->id)
        ->and($purchase->posted_at)->not->toBeNull();

    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Purchase->value,
        'reference_id' => $purchase->id,
        'quantity_in' => 40,
        'quantity_out' => 0,
    ]);

    expect($product->stockQuantity())->toBe('40');
});

test('posted purchase cannot be updated or deleted', function () {
    $admin = User::factory()->administrator()->create();
    $purchase = Purchase::factory()->posted($admin)->withLines(1)->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('purchases.store-update', $purchase), [
            'purchase_date' => '2026-08-08',
            'supplier_id' => $supplier->id,
            'notes' => 'محاولة تعديل',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('purchases.destroy', $purchase))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('purchases.post', $purchase))
        ->assertForbidden();
});

test('sales role cannot manage purchases', function () {
    $user = User::factory()->create(['role' => UserRole::Sales]);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertForbidden();
});

test('draft purchase requires a receipt number', function () {
    $admin = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('purchases.store-update'), [
            'purchase_date' => '2026-08-07',
            'receipt_number' => '   ',
            'supplier_id' => $supplier->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('receipt_number');

    expect(Purchase::query()->count())->toBe(0);
});

test('purchases index can search by receipt number', function () {
    $admin = User::factory()->administrator()->create();
    $match = Purchase::factory()->create(['receipt_number' => 'وصل-8841']);
    Purchase::factory()->create(['receipt_number' => 'وصل-1000']);

    $this->actingAs($admin)
        ->get(route('purchases.index', ['search' => '8841']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.id', $match->id)
            ->where('purchases.data.0.receipt_number', 'وصل-8841'));
});

test('draft purchase without lines cannot be posted', function () {
    $admin = User::factory()->administrator()->create();
    $purchase = Purchase::factory()->create();

    $this->actingAs($admin)
        ->post(route('purchases.post', $purchase))
        ->assertRedirect();

    expect($purchase->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

test('updating a draft purchase replaces lines', function () {
    $admin = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $oldProduct = Product::factory()->create(['is_active' => true]);
    $newProduct = Product::factory()->create(['is_active' => true]);

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => DocumentStatus::Draft,
    ]);

    PurchaseLine::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $oldProduct->id,
        'quantity' => 5,
    ]);

    $this->actingAs($admin)
        ->post(route('purchases.store-update', $purchase), [
            'purchase_date' => '2026-08-09',
            'receipt_number' => 'وصل-2200',
            'supplier_id' => $supplier->id,
            'notes' => null,
            'lines' => [
                ['product_id' => $newProduct->id, 'quantity' => 8],
            ],
        ])
        ->assertRedirect(route('purchases.create-edit', $purchase));

    $purchase->refresh()->load('lines');

    expect($purchase->receipt_number)->toBe('وصل-2200')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->lines->first()->product_id)->toBe($newProduct->id)
        ->and((string) $purchase->lines->first()->quantity)->toBe('8.000');
});

test('cancelling a posted purchase reverses inventory and sets cancelled status', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create();
    $purchase = Purchase::factory()
        ->for(Supplier::factory())
        ->create(['status' => DocumentStatus::Draft]);

    PurchaseLine::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 40,
    ]);

    $this->actingAs($admin)
        ->post(route('purchases.post', $purchase))
        ->assertRedirect(route('purchases.index'));

    expect($product->stockQuantity())->toBe('40');

    $this->actingAs($admin)
        ->post(route('purchases.cancel', $purchase))
        ->assertRedirect(route('purchases.index'));

    $purchase->refresh();

    expect($purchase->status)->toBe(DocumentStatus::Cancelled)
        ->and($purchase->posted_at)->not->toBeNull()
        ->and($purchase->posted_by)->toBe($admin->id);

    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Purchase->value,
        'reference_id' => $purchase->id,
        'quantity_in' => 0,
        'quantity_out' => 40,
    ]);

    expect($product->stockQuantity())->toBe('0');
});

test('cancelled purchases are hidden from the default index and shown when filtered', function () {
    $admin = User::factory()->administrator()->create();
    $draft = Purchase::factory()->create(['status' => DocumentStatus::Draft]);
    $posted = Purchase::factory()->posted($admin)->create();
    $cancelled = Purchase::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->get(route('purchases.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/index')
            ->has('purchases.data', 2)
            ->where('purchases.data.0.id', $posted->id)
            ->where('purchases.data.1.id', $draft->id)
            ->where('filters.status', ''));

    $this->actingAs($admin)
        ->get(route('purchases.index', ['status' => 'cancelled']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.id', $cancelled->id)
            ->where('filters.status', 'cancelled'));
});

test('purchases index can filter by supplier and date range', function () {
    $admin = User::factory()->administrator()->create();
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $match = Purchase::factory()->create([
        'supplier_id' => $supplierA->id,
        'purchase_date' => '2026-08-10',
        'status' => DocumentStatus::Draft,
    ]);
    Purchase::factory()->create([
        'supplier_id' => $supplierB->id,
        'purchase_date' => '2026-08-10',
        'status' => DocumentStatus::Draft,
    ]);
    Purchase::factory()->create([
        'supplier_id' => $supplierA->id,
        'purchase_date' => '2026-07-01',
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->get(route('purchases.index', [
            'supplier_id' => $supplierA->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.id', $match->id)
            ->where('filters.supplier_id', $supplierA->id)
            ->where('filters.from_date', '2026-08-01')
            ->where('filters.to_date', '2026-08-31')
            ->where('selected_supplier.value', $supplierA->id));
});

test('draft purchase cannot be cancelled and cancelled purchase cannot be cancelled again', function () {
    $admin = User::factory()->administrator()->create();
    $draft = Purchase::factory()->create();
    $cancelled = Purchase::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->post(route('purchases.cancel', $draft))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('purchases.cancel', $cancelled))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('purchases.destroy', $cancelled))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('purchases.post', $cancelled))
        ->assertForbidden();
});
