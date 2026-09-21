<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;

test('authorized users can search lookup endpoints', function () {
    $admin = User::factory()->administrator()->create();
    $category = Category::factory()->create(['name' => 'صنف بحث']);
    $unit = Unit::factory()->create(['name' => 'وحدة بحث']);
    $product = Product::factory()->create([
        'name_ar' => 'منتج بحث',
        'code' => 'LK-1',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
    ]);
    $supplier = Supplier::factory()->create(['name' => 'مورد بحث']);
    $distributor = Distributor::factory()->create(['name' => 'موزع بحث']);

    $this->actingAs($admin)
        ->getJson(route('lookups.products', ['search' => 'بحث']))
        ->assertOk()
        ->assertJsonFragment([
            'value' => $product->id,
            'label' => 'LK-1 — منتج بحث',
        ]);

    $productWithoutCode = Product::factory()->create([
        'name_ar' => 'منتج بلا رمز',
        'code' => null,
        'category_id' => $category->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('lookups.products', ['search' => 'بلا رمز']))
        ->assertOk()
        ->assertJsonFragment([
            'value' => $productWithoutCode->id,
            'label' => 'منتج بلا رمز',
        ]);

    $this->actingAs($admin)
        ->getJson(route('lookups.suppliers', ['search' => 'بحث']))
        ->assertOk()
        ->assertJsonFragment(['value' => $supplier->id]);

    $this->actingAs($admin)
        ->getJson(route('lookups.distributors', ['search' => 'بحث']))
        ->assertOk()
        ->assertJsonFragment(['value' => $distributor->id]);

    $this->actingAs($admin)
        ->getJson(route('lookups.categories', ['search' => 'بحث']))
        ->assertOk()
        ->assertJsonFragment(['value' => $category->id]);

    $this->actingAs($admin)
        ->getJson(route('lookups.units', ['search' => 'بحث']))
        ->assertOk()
        ->assertJsonFragment(['value' => $unit->id]);

    $this->actingAs($admin)
        ->getJson(route('lookups.open-invoices', [
            'distributor_id' => $distributor->id,
        ]))
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('product lookup includes last posted selling price and barcode meta', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create([
        'name_ar' => 'منتج سعر',
        'code' => 'PR-9',
        'barcode' => '6281000000001',
    ]);

    $draft = SalesInvoice::factory()->create();
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $draft->id,
        'product_id' => $product->id,
        'unit_price' => 99,
    ]);

    $older = SalesInvoice::factory()->posted($admin)->create([
        'invoice_date' => '2026-01-01',
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $older->id,
        'product_id' => $product->id,
        'unit_price' => 10,
    ]);

    $newer = SalesInvoice::factory()->posted($admin)->create([
        'invoice_date' => '2026-08-01',
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $newer->id,
        'product_id' => $product->id,
        'unit_price' => 12.50,
    ]);

    $this->actingAs($admin)
        ->getJson(route('lookups.products', ['search' => '6281000000001']))
        ->assertOk()
        ->assertJsonPath('data.0.value', $product->id)
        ->assertJsonPath('data.0.label', 'PR-9 — منتج سعر')
        ->assertJsonPath('data.0.meta.code', 'PR-9')
        ->assertJsonPath('data.0.meta.barcode', '6281000000001')
        ->assertJsonPath('data.0.meta.name_ar', 'منتج سعر')
        ->assertJsonPath('data.0.meta.unit', $product->unit->name)
        ->assertJsonPath('data.0.meta.last_unit_price', '12.5');
});

test('product lookup last price is empty when the product was never sold', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create([
        'name_ar' => 'منتج جديد',
        'code' => 'PR-NEW',
        'barcode' => null,
    ]);

    $this->actingAs($admin)
        ->getJson(route('lookups.products', ['search' => 'منتج جديد']))
        ->assertOk()
        ->assertJsonPath('data.0.value', $product->id)
        ->assertJsonPath('data.0.meta.last_unit_price', null);
});

test('sales role can lookup products for invoices', function () {
    $user = User::factory()->create(['role' => UserRole::Sales]);
    Product::factory()->create(['name_ar' => 'منتج مبيعات', 'is_active' => true]);

    $this->actingAs($user)
        ->getJson(route('lookups.products', ['search' => 'مبيعات']))
        ->assertOk();
});

test('accountant cannot lookup products', function () {
    $user = User::factory()->create(['role' => UserRole::Accountant]);

    $this->actingAs($user)
        ->getJson(route('lookups.products'))
        ->assertForbidden();
});

test('guests cannot access lookups', function () {
    $this->getJson(route('lookups.products'))->assertUnauthorized();
});
