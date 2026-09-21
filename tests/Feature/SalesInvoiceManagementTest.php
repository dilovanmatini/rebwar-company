<?php

use App\Enums\DocumentStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\LedgerReferenceType;
use App\Enums\UserRole;
use App\Models\AccountsReceivableEntry;
use App\Models\CustomerLedgerEntry;
use App\Models\Distributor;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('administrator can create a draft sales invoice with totals', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('sales-invoices.store-update'), [
            'invoice_date' => '2026-08-07',
            'distributor_id' => $distributor->id,
            'notes' => 'اختبار',
            'discount' => 10,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ])
        ->assertRedirect(route('sales-invoices.create-edit', SalesInvoice::query()->first()));

    $invoice = SalesInvoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->number)->toStartWith('INV-')
        ->and($invoice->status)->toBe(DocumentStatus::Draft)
        ->and((string) $invoice->subtotal)->toBe('100.00')
        ->and((string) $invoice->discount)->toBe('10.00')
        ->and((string) $invoice->grand_total)->toBe('90.00')
        ->and($invoice->lines)->toHaveCount(1)
        ->and((string) $invoice->lines->first()->line_total)->toBe('100.00');
});

test('sales invoice create page loads without prefilled lines', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('sales-invoices.create-edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('sales-invoices/create-edit')
            ->where('invoice', null)
            ->where('selected_products', [])
            ->where('can_edit', true));
});

test('sales invoice create-edit trims trailing zeros on money fields', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create();

    $invoice = SalesInvoice::factory()->create([
        'subtotal' => 100,
        'discount' => 1,
        'grand_total' => 98.95,
        'status' => DocumentStatus::Draft,
    ]);

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'line_total' => 100,
    ]);

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 2.05,
        'line_total' => 2.05,
    ]);

    // Force stored decimal padding like the DB returns for whole amounts.
    $invoice->update(['discount' => '1.00']);

    $this->actingAs($admin)
        ->get(route('sales-invoices.create-edit', $invoice))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('sales-invoices/create-edit')
            ->where('invoice.discount', '1')
            ->where('invoice.subtotal', '100')
            ->where('invoice.grand_total', '98.95')
            ->where('invoice.lines.0.unit_price', '50')
            ->where('invoice.lines.0.quantity', '2')
            ->where('invoice.lines.1.unit_price', '2.05')
            ->where('selected_products.0.value', $product->id)
            ->where('selected_products.0.meta.name_ar', $product->name_ar)
            ->where('selected_products.0.meta.code', $product->code));
});

test('posting a sales invoice decreases stock and creates ledger entries', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();
    $product = Product::factory()->create();

    InventoryTransaction::factory()->create([
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Purchase,
        'quantity_in' => 100,
        'quantity_out' => 0,
    ]);

    $invoice = SalesInvoice::factory()->create([
        'distributor_id' => $distributor->id,
        'subtotal' => 200,
        'discount' => 20,
        'grand_total' => 180,
        'status' => DocumentStatus::Draft,
    ]);

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 15,
        'unit_price' => 12,
        'line_total' => 180,
    ]);

    $this->actingAs($admin)
        ->post(route('sales-invoices.post', $invoice))
        ->assertRedirect(route('sales-invoices.index'));

    $invoice->refresh();

    expect($invoice->status)->toBe(DocumentStatus::Posted)
        ->and($invoice->posted_by)->toBe($admin->id)
        ->and($product->stockQuantity())->toBe('85')
        ->and($distributor->balance())->toBe('180');

    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Sale->value,
        'reference_id' => $invoice->id,
        'quantity_out' => 15,
    ]);

    $this->assertDatabaseHas('customer_ledger_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice->value,
        'reference_id' => $invoice->id,
        'debit' => 180,
        'credit' => 0,
    ]);

    $this->assertDatabaseHas('accounts_receivable_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice->value,
        'reference_id' => $invoice->id,
        'debit' => 180,
        'credit' => 0,
    ]);
});

test('cannot post a sales invoice when stock is insufficient', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create();

    InventoryTransaction::factory()->create([
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Purchase,
        'quantity_in' => 5,
        'quantity_out' => 0,
    ]);

    $invoice = SalesInvoice::factory()->create([
        'subtotal' => 100,
        'discount' => 0,
        'grand_total' => 100,
    ]);

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 10,
        'line_total' => 100,
    ]);

    $this->actingAs($admin)
        ->post(route('sales-invoices.post', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(CustomerLedgerEntry::query()->count())->toBe(0)
        ->and(AccountsReceivableEntry::query()->count())->toBe(0)
        ->and($product->stockQuantity())->toBe('5');
});

test('cancelling a posted sales invoice reverses inventory and ledger entries', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();
    $product = Product::factory()->create();

    InventoryTransaction::factory()->create([
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Purchase,
        'quantity_in' => 100,
        'quantity_out' => 0,
    ]);

    $invoice = SalesInvoice::factory()->create([
        'distributor_id' => $distributor->id,
        'subtotal' => 200,
        'discount' => 20,
        'grand_total' => 180,
        'status' => DocumentStatus::Draft,
    ]);

    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 15,
        'unit_price' => 12,
        'line_total' => 180,
    ]);

    $this->actingAs($admin)
        ->post(route('sales-invoices.post', $invoice))
        ->assertRedirect(route('sales-invoices.index'));

    expect($product->stockQuantity())->toBe('85')
        ->and($distributor->balance())->toBe('180');

    $this->actingAs($admin)
        ->post(route('sales-invoices.cancel', $invoice))
        ->assertRedirect(route('sales-invoices.index'));

    $invoice->refresh();

    expect($invoice->status)->toBe(DocumentStatus::Cancelled)
        ->and($product->stockQuantity())->toBe('100')
        ->and($distributor->balance())->toBe('0');

    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'reference_type' => InventoryReferenceType::Sale->value,
        'reference_id' => $invoice->id,
        'quantity_in' => 15,
        'quantity_out' => 0,
    ]);

    $this->assertDatabaseHas('customer_ledger_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice->value,
        'reference_id' => $invoice->id,
        'debit' => 0,
        'credit' => 180,
    ]);

    $this->assertDatabaseHas('accounts_receivable_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice->value,
        'reference_id' => $invoice->id,
        'debit' => 0,
        'credit' => 180,
    ]);
});

test('cancelled sales invoices are hidden from the default index and shown when filtered', function () {
    $admin = User::factory()->administrator()->create();
    $draft = SalesInvoice::factory()->create(['status' => DocumentStatus::Draft]);
    $posted = SalesInvoice::factory()->posted($admin)->create();
    $cancelled = SalesInvoice::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->get(route('sales-invoices.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('sales-invoices/index')
            ->has('invoices.data', 2)
            ->where('invoices.data.0.id', $posted->id)
            ->where('invoices.data.1.id', $draft->id)
            ->where('filters.status', ''));

    $this->actingAs($admin)
        ->get(route('sales-invoices.index', ['status' => 'cancelled']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('sales-invoices/index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $cancelled->id)
            ->where('filters.status', 'cancelled'));
});

test('sales invoices index can filter by distributor and date range', function () {
    $admin = User::factory()->administrator()->create();
    $distributorA = Distributor::factory()->create();
    $distributorB = Distributor::factory()->create();

    $match = SalesInvoice::factory()->create([
        'distributor_id' => $distributorA->id,
        'invoice_date' => '2026-08-10',
        'status' => DocumentStatus::Draft,
        'subtotal' => 150,
        'discount' => 10,
        'grand_total' => 140,
    ]);
    SalesInvoice::factory()->create([
        'distributor_id' => $distributorB->id,
        'invoice_date' => '2026-08-10',
        'status' => DocumentStatus::Draft,
    ]);
    SalesInvoice::factory()->create([
        'distributor_id' => $distributorA->id,
        'invoice_date' => '2026-07-01',
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->get(route('sales-invoices.index', [
            'distributor_id' => $distributorA->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('sales-invoices/index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $match->id)
            ->where('invoices.data.0.subtotal', '150.00 $')
            ->where('invoices.data.0.discount', '10.00 $')
            ->where('invoices.data.0.grand_total', '140.00 $')
            ->where('filters.distributor_id', $distributorA->id)
            ->where('filters.from_date', '2026-08-01')
            ->where('filters.to_date', '2026-08-31')
            ->where('selected_distributor.value', $distributorA->id));
});

test('draft sales invoice cannot be cancelled and cancelled invoice cannot be cancelled again', function () {
    $admin = User::factory()->administrator()->create();
    $draft = SalesInvoice::factory()->create();
    $cancelled = SalesInvoice::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->post(route('sales-invoices.cancel', $draft))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('sales-invoices.cancel', $cancelled))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('sales-invoices.destroy', $cancelled))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('sales-invoices.post', $cancelled))
        ->assertForbidden();
});

test('posted sales invoice cannot be updated or deleted', function () {
    $admin = User::factory()->administrator()->create();
    $invoice = SalesInvoice::factory()->posted($admin)->withLines(1)->create();
    $distributor = Distributor::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('sales-invoices.store-update', $invoice), [
            'invoice_date' => '2026-08-08',
            'distributor_id' => $distributor->id,
            'notes' => 'محاولة تعديل',
            'discount' => 0,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('sales-invoices.destroy', $invoice))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('sales-invoices.post', $invoice))
        ->assertForbidden();
});

test('posted sales invoice can be printed and draft cannot', function () {
    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create();

    $posted = SalesInvoice::factory()->posted($admin)->create([
        'subtotal' => 100,
        'discount' => 0,
        'grand_total' => 100,
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $posted->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'line_total' => 100,
    ]);

    $draft = SalesInvoice::factory()->create();
    $cancelled = SalesInvoice::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->get(route('sales-invoices.create-edit', $posted))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('sales-invoices/create-edit')
            ->where('can_print', true)
            ->where('can_cancel', true)
            ->where('can_edit', false));

    $this->actingAs($admin)
        ->get(route('sales-invoices.print', $posted))
        ->assertSuccessful()
        ->assertSee($posted->number, false)
        ->assertSee('REBWAR CO.', false)
        ->assertSee('For General Trading', false)
        ->assertSee('للتجارة العامة', false)
        ->assertSee('حضرة السيد', false)
        ->assertSee('العنوان', false)
        ->assertSee('التفاصيل', false)
        ->assertSee('العدد', false)
        ->assertSee('السعر', false)
        ->assertSee('الخصم', false)
        ->assertSee('المبلغ', false)
        ->assertSee('المجموع الأولي', false)
        ->assertSee('المجموع النهائي', false)
        ->assertSee('اسم السائق', false)
        ->assertSee('رقم السيارة', false)
        ->assertSee('الغلط و السهو مرجوع للطرفين.', false)
        ->assertSee('DUHOK', false)
        ->assertSee($posted->distributor->name, false)
        ->assertSee('>50.00</td>', false)
        ->assertSee('>100.00</td>', false)
        ->assertSee($posted->invoice_date?->format('d-m-Y'), false)
        ->assertDontSee('المبلغ المستحق', false)
        ->assertDontSee('المبلغ كتابةً', false)
        ->assertSee('/images/logo.png', false)
        ->assertSee('IBM Plex Sans Arabic', false)
        ->assertSee('/fonts/IBMPlexSansArabic-Regular.ttf', false)
        ->assertSee('window.print()', false);

    $this->actingAs($admin)
        ->get(route('sales-invoices.print', $draft))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('sales-invoices.print', $cancelled))
        ->assertForbidden();
});

test('posted sales invoice print uses uploaded logo when present', function () {
    Storage::fake('public');

    $admin = User::factory()->administrator()->create();
    $product = Product::factory()->create();

    $settings = SystemSetting::current();
    $path = UploadedFile::fake()->image('brand.png')->store('logos', 'public');
    $settings->update(['logo_path' => $path]);

    $posted = SalesInvoice::factory()->posted($admin)->create();
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $posted->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($admin)
        ->get(route('sales-invoices.print', $posted))
        ->assertSuccessful()
        ->assertSee(Storage::disk('public')->url($path), false)
        ->assertDontSee('/images/logo.png', false);
});

test('posted sales invoice print uses invoice header footer and shows discount', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create([
        'name' => 'اسماعيل سيتو',
        'address' => 'شاريا',
    ]);
    $product = Product::factory()->create(['name_ar' => 'بيرة فترة دبل']);

    SystemSetting::current()->update([
        'invoice_header' => 'رأس فاتورة مخصص',
        'invoice_footer' => 'تذييل فاتورة مخصص',
    ]);

    $posted = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'subtotal' => 100,
        'discount' => 10,
        'grand_total' => 90,
    ]);
    SalesInvoiceLine::factory()->create([
        'sales_invoice_id' => $posted->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'line_total' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('sales-invoices.print', $posted))
        ->assertSuccessful()
        ->assertSee('رأس فاتورة مخصص', false)
        ->assertSee('تذييل فاتورة مخصص', false)
        ->assertSee('اسماعيل سيتو', false)
        ->assertSee('شاريا', false)
        ->assertSee('بيرة فترة دبل', false)
        ->assertSee('الخصم:', false)
        ->assertSee('>10.00</span>', false)
        ->assertSee('>90.00</span>', false)
        ->assertDontSee('DUHOK', false)
        ->assertDontSee('الغلط و السهو مرجوع للطرفين.', false);
});

test('warehouse role cannot manage sales invoices', function () {
    $user = User::factory()->create(['role' => UserRole::Warehouse]);

    $this->actingAs($user)
        ->get(route('sales-invoices.index'))
        ->assertForbidden();
});
