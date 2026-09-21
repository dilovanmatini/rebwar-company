<?php

use App\Enums\DocumentStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReportBuilder;

test('inventory print view groups products by category with quantities and selling prices', function () {
    $admin = User::factory()->administrator()->create();
    SystemSetting::current()->update(['app_name' => 'شركة ربوار']);

    $carton = Unit::factory()->create(['name' => 'كارتون']);
    $beer = Category::factory()->create(['name' => 'بيرة']);
    $gin = Category::factory()->create(['name' => 'جن']);

    $lager = Product::factory()->create([
        'name_ar' => 'بيرة بارة 10%',
        'category_id' => $beer->id,
        'unit_id' => $carton->id,
    ]);
    $zeroStockGin = Product::factory()->create([
        'name_ar' => 'جن كادبوري',
        'category_id' => $gin->id,
        'unit_id' => $carton->id,
    ]);

    InventoryTransaction::factory()->create([
        'product_id' => $lager->id,
        'reference_type' => InventoryReferenceType::Purchase,
        'quantity_in' => 18112,
        'quantity_out' => 0,
    ]);

    $invoice = SalesInvoice::factory()->posted($admin)->create([
        'invoice_date' => '2026-09-01',
        'status' => DocumentStatus::Posted,
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $lager->id,
        'quantity' => 2,
        'unit_price' => 12.5,
        'line_total' => 25,
    ]);

    $this->actingAs($admin)
        ->get(route('reports.print', 'inventory'))
        ->assertSuccessful()
        ->assertSee('تقرير المخزون', false)
        ->assertSee('شركة ربوار', false)
        ->assertSee('الموقع : المخزن', false)
        ->assertSee('وصف المنتج', false)
        ->assertSee('الكمية', false)
        ->assertSee('سعر البيع', false)
        ->assertSee('بيرة', false)
        ->assertSee('بيرة بارة 10%', false)
        ->assertSee('18,112.00', false)
        ->assertSee('كارتون', false)
        ->assertSee('جن', false)
        ->assertSee($zeroStockGin->name_ar, false)
        ->assertSee('0.00', false)
        ->assertSee('المجموع حسب المجموعة', false)
        ->assertSee('الإجمالي', false)
        ->assertSee('window.print()', false);
});

test('inventory pdf export uses the grouped inventory layout', function () {
    $admin = User::factory()->administrator()->create();
    Product::factory()->create(['name_ar' => 'منتج للطباعة']);

    $this->actingAs($admin)
        ->get(route('reports.pdf', 'inventory'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

test('inventory report builder groups products by category and totals quantity', function () {
    $admin = User::factory()->administrator()->create();
    $carton = Unit::factory()->create(['name' => 'كارتون']);
    $beer = Category::factory()->create(['name' => 'بيرة']);
    $gin = Category::factory()->create(['name' => 'جن']);

    $lager = Product::factory()->create([
        'name_ar' => 'بيرة بارة 10%',
        'category_id' => $beer->id,
        'unit_id' => $carton->id,
    ]);
    Product::factory()->create([
        'name_ar' => 'جن كادبوري',
        'category_id' => $gin->id,
        'unit_id' => $carton->id,
    ]);

    InventoryTransaction::factory()->create([
        'product_id' => $lager->id,
        'reference_type' => InventoryReferenceType::Purchase,
        'quantity_in' => 18112,
        'quantity_out' => 0,
    ]);

    $invoice = SalesInvoice::factory()->posted($admin)->create([
        'invoice_date' => '2026-09-01',
        'status' => DocumentStatus::Posted,
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $lager->id,
        'quantity' => 2,
        'unit_price' => 12.5,
        'line_total' => 25,
    ]);

    $report = app(InventoryReportBuilder::class)->build();
    $beerGroup = collect($report['groups'])->firstWhere('name', 'بيرة');
    $ginGroup = collect($report['groups'])->firstWhere('name', 'جن');

    expect($report['groups'])->toHaveCount(2)
        ->and($beerGroup['rows'])->toHaveCount(1)
        ->and($beerGroup['rows'][0]['name'])->toBe('بيرة بارة 10%')
        ->and($beerGroup['rows'][0]['quantity'])->toBe('18,112.00')
        ->and($beerGroup['rows'][0]['unit'])->toBe('كارتون')
        ->and($beerGroup['totals']['quantity'])->toBe('18,112.00')
        ->and($ginGroup['rows'][0]['quantity'])->toBe('0.00')
        ->and($report['grand_totals']['name'])->toBe('الإجمالي')
        ->and($report['grand_totals']['quantity'])->toBe('18,112.00');
});

test('warehouse role cannot print the inventory report', function () {
    $user = User::factory()->create(['role' => UserRole::Warehouse]);

    $this->actingAs($user)
        ->get(route('reports.print', 'inventory'))
        ->assertForbidden();
});
