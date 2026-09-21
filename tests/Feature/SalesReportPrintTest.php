<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SalesByProductReportBuilder;
use Carbon\Carbon;

test('sales report print groups products by salesman and customer', function () {
    $admin = User::factory()->administrator()->create();
    $salesman = User::factory()->create(['name' => 'أزاد سالم', 'role' => UserRole::Sales]);
    $customer = Distributor::factory()->create(['name' => 'مكتب ديار']);
    $beer = Product::factory()->create(['name_ar' => 'بيرة بيرة معلب كبير']);
    $whisky = Product::factory()->create(['name_ar' => 'ويسكي كيلين معلب']);

    $invoice = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $customer->id,
        'invoice_date' => '2026-08-01',
        'subtotal' => 1210,
        'discount' => 0,
        'grand_total' => 1210,
    ]);
    $invoice->forceFill(['created_by' => $salesman->id])->save();

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $beer->id,
        'quantity' => 20,
        'unit_price' => 20.50,
        'line_total' => 410,
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $whisky->id,
        'quantity' => 10,
        'unit_price' => 80,
        'line_total' => 800,
    ]);

    $this->actingAs($admin)
        ->get(route('reports.print', [
            'report' => 'sales',
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-14',
        ]))
        ->assertSuccessful()
        ->assertSee('تقرير المبيعات', false)
        ->assertSee('المبيعات حسب المندوب حسب العميل حسب المنتج', false)
        ->assertSee('اسم العميل: مكتب ديار', false)
        ->assertSee('أزاد سالم', false)
        ->assertSee('بيرة بيرة معلب كبير', false)
        ->assertSee('ويسكي كيلين معلب', false)
        ->assertSee('البيان', false)
        ->assertSee('الكمية', false)
        ->assertSee('20.00', false)
        ->assertSee('10.00', false)
        ->assertSee('الإجمالي', false);
});

test('sales print report aggregates the same product across posted invoices', function () {
    $admin = User::factory()->administrator()->create();
    $salesman = User::factory()->create(['name' => 'مندوب واحد']);
    $customer = Distributor::factory()->create(['name' => 'أسواق جلال']);
    $product = Product::factory()->create(['name_ar' => 'منتج مجمع']);

    foreach ([['2026-03-01', 5, 50], ['2026-03-10', 7, 70]] as [$date, $quantity, $total]) {
        $invoice = SalesInvoice::factory()->posted($admin)->create([
            'distributor_id' => $customer->id,
            'invoice_date' => $date,
            'subtotal' => $total,
            'discount' => 0,
            'grand_total' => $total,
        ]);
        $invoice->forceFill(['created_by' => $salesman->id])->save();

        SalesInvoiceLine::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 10,
            'line_total' => $total,
        ]);
    }

    SalesInvoice::factory()->create([
        'distributor_id' => $customer->id,
        'invoice_date' => '2026-03-05',
        'status' => DocumentStatus::Draft,
        'subtotal' => 999,
        'grand_total' => 999,
    ]);

    $report = app(SalesByProductReportBuilder::class)->build(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
    );

    expect($report['salesmen'])->toHaveCount(1)
        ->and($report['salesmen'][0]['name'])->toBe('مندوب واحد')
        ->and($report['salesmen'][0]['customers'])->toHaveCount(1)
        ->and($report['salesmen'][0]['customers'][0]['name'])->toBe('أسواق جلال')
        ->and($report['salesmen'][0]['customers'][0]['products'])->toHaveCount(1)
        ->and($report['salesmen'][0]['customers'][0]['products'][0]['name'])->toBe('منتج مجمع')
        ->and($report['salesmen'][0]['customers'][0]['products'][0]['quantity'])->toBe('12.00')
        ->and($report['salesmen'][0]['customers'][0]['products'][0]['percent'])->toBe('100.00')
        ->and($report['totals']['quantity'])->toBe('12.00')
        ->and($report['totals']['percent'])->toBe('100.00');
});

test('sales report print falls back to the poster when the creator is missing', function () {
    $admin = User::factory()->administrator()->create(['name' => 'مدير الترحيل']);
    $customer = Distributor::factory()->create(['name' => 'عميل الملصق']);
    $product = Product::factory()->create(['name_ar' => 'منتج الملصق']);

    $invoice = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $customer->id,
        'invoice_date' => '2026-04-01',
        'subtotal' => 25,
        'discount' => 0,
        'grand_total' => 25,
    ]);
    $invoice->forceFill(['created_by' => null])->save();

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 25,
        'line_total' => 25,
    ]);

    $report = app(SalesByProductReportBuilder::class)->build();

    expect($report['salesmen'][0]['name'])->toBe('مدير الترحيل');
});

test('sales report pdf export uses the grouped print layout', function () {
    $admin = User::factory()->administrator()->create();
    SystemSetting::current()->update(['app_name' => 'شركة ريبوار']);

    $this->actingAs($admin)
        ->get(route('reports.pdf', 'sales'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});
