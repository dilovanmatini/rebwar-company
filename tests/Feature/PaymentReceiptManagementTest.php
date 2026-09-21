<?php

use App\Enums\Currency;
use App\Enums\DocumentStatus;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\AccountsReceivableEntry;
use App\Models\CustomerLedgerEntry;
use App\Models\Distributor;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('payment receipt form lists digital wallets after cash', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('payment-receipts.create-edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payment-receipts/create-edit')
            ->missing('selected_invoices')
            ->where('receipt', null)
            ->where('payment_methods', [
                ['value' => 'cash', 'label' => 'نقداً'],
                ['value' => 'fib', 'label' => 'FIB'],
                ['value' => 'qi_card', 'label' => 'Qi Card'],
                ['value' => 'fastpay', 'label' => 'FastPay'],
                ['value' => 'bank_transfer', 'label' => 'تحويل بنكي'],
                ['value' => 'cheque', 'label' => 'شيك'],
                ['value' => 'other', 'label' => 'أخرى'],
            ]));
});

test('administrator can create a draft payment receipt with an amount', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('payment-receipts.store-update'), [
            'receipt_date' => '2026-08-07',
            'distributor_id' => $distributor->id,
            'payment_method' => PaymentMethod::Cash->value,
            'amount' => 150,
            'notes' => 'دفعة جزئية',
        ])
        ->assertRedirect(route('payment-receipts.create-edit', PaymentReceipt::query()->first()));

    $receipt = PaymentReceipt::query()->first();

    expect($receipt)->not->toBeNull()
        ->and($receipt->number)->toStartWith('REC-')
        ->and($receipt->status)->toBe(DocumentStatus::Draft)
        ->and((string) $receipt->amount)->toBe('150.00')
        ->and($receipt->allocations)->toHaveCount(0);
});

test('posting a payment receipt creates ledger credits and reduces balance', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();
    $invoice = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'grand_total' => 200,
        'subtotal' => 200,
        'discount' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $invoice->id,
        'debit' => 200,
        'credit' => 0,
    ]);

    AccountsReceivableEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $invoice->id,
        'debit' => 200,
        'credit' => 0,
    ]);

    $receipt = PaymentReceipt::factory()->create([
        'distributor_id' => $distributor->id,
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => DocumentStatus::Draft,
        'amount' => 75,
    ]);

    $this->actingAs($admin)
        ->post(route('payment-receipts.post', $receipt))
        ->assertRedirect(route('payment-receipts.index'));

    $receipt->refresh();

    expect($receipt->status)->toBe(DocumentStatus::Posted)
        ->and($receipt->posted_by)->toBe($admin->id)
        ->and($distributor->balance())->toBe('125')
        ->and($invoice->remainingAmount())->toBe('125.00');

    $this->assertDatabaseHas('customer_ledger_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Receipt->value,
        'reference_id' => $receipt->id,
        'debit' => 0,
        'credit' => 75,
    ]);

    $this->assertDatabaseHas('accounts_receivable_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Receipt->value,
        'reference_id' => $receipt->id,
        'debit' => 0,
        'credit' => 75,
    ]);
});

test('cancelling a posted payment receipt reverses ledger entries and restores balances', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();
    $invoice = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'grand_total' => 200,
        'subtotal' => 200,
        'discount' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $invoice->id,
        'debit' => 200,
        'credit' => 0,
    ]);

    AccountsReceivableEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $invoice->id,
        'debit' => 200,
        'credit' => 0,
    ]);

    $receipt = PaymentReceipt::factory()->create([
        'distributor_id' => $distributor->id,
        'status' => DocumentStatus::Draft,
        'amount' => 75,
    ]);

    $this->actingAs($admin)
        ->post(route('payment-receipts.post', $receipt))
        ->assertRedirect(route('payment-receipts.index'));

    expect($distributor->balance())->toBe('125')
        ->and($invoice->remainingAmount())->toBe('125.00');

    $this->actingAs($admin)
        ->post(route('payment-receipts.cancel', $receipt))
        ->assertRedirect(route('payment-receipts.index'));

    $receipt->refresh();

    expect($receipt->status)->toBe(DocumentStatus::Cancelled)
        ->and($distributor->balance())->toBe('200')
        ->and($invoice->remainingAmount())->toBe('200.00');

    $this->assertDatabaseHas('customer_ledger_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Receipt->value,
        'reference_id' => $receipt->id,
        'debit' => 75,
        'credit' => 0,
    ]);

    $this->assertDatabaseHas('accounts_receivable_entries', [
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Receipt->value,
        'reference_id' => $receipt->id,
        'debit' => 75,
        'credit' => 0,
    ]);
});

test('cancelled payment receipts are hidden from the default index and shown when filtered', function () {
    $admin = User::factory()->administrator()->create();
    $draft = PaymentReceipt::factory()->create(['status' => DocumentStatus::Draft]);
    $posted = PaymentReceipt::factory()->posted($admin)->create();
    $cancelled = PaymentReceipt::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->get(route('payment-receipts.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('payment-receipts/index')
            ->has('receipts.data', 2)
            ->where('receipts.data.0.id', $posted->id)
            ->where('receipts.data.1.id', $draft->id)
            ->where('filters.status', ''));

    $this->actingAs($admin)
        ->get(route('payment-receipts.index', ['status' => 'cancelled']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('payment-receipts/index')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.id', $cancelled->id)
            ->where('filters.status', 'cancelled'));
});

test('draft payment receipt cannot be cancelled and cancelled receipt cannot be cancelled again', function () {
    $admin = User::factory()->administrator()->create();
    $draft = PaymentReceipt::factory()->create();
    $cancelled = PaymentReceipt::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->post(route('payment-receipts.cancel', $draft))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('payment-receipts.cancel', $cancelled))
        ->assertForbidden();
});

test('draft payment receipt requires a positive amount', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('payment-receipts.store-update'), [
            'receipt_date' => '2026-08-07',
            'distributor_id' => $distributor->id,
            'payment_method' => PaymentMethod::Cash->value,
            'notes' => null,
            'amount' => 0,
        ])
        ->assertSessionHasErrors('amount');

    expect(PaymentReceipt::query()->count())->toBe(0);
});

test('posting a receipt applies the amount to the oldest open invoices', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create(['is_active' => true]);

    $first = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'invoice_date' => '2026-08-01',
        'grand_total' => 100,
        'subtotal' => 100,
        'discount' => 0,
    ]);
    $second = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'invoice_date' => '2026-08-02',
        'grand_total' => 80,
        'subtotal' => 80,
        'discount' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $first->id,
        'debit' => 100,
        'credit' => 0,
    ]);
    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $second->id,
        'debit' => 80,
        'credit' => 0,
    ]);

    $receipt = PaymentReceipt::factory()->create([
        'distributor_id' => $distributor->id,
        'status' => DocumentStatus::Draft,
        'amount' => 120,
    ]);

    $this->actingAs($admin)
        ->post(route('payment-receipts.post', $receipt))
        ->assertRedirect(route('payment-receipts.index'));

    expect($first->remainingAmount())->toBe('0.00')
        ->and($second->remainingAmount())->toBe('60.00')
        ->and($distributor->balance())->toBe('60')
        ->and($receipt->allocations)->toHaveCount(2);
});

test('posted payment receipt cannot be updated or deleted', function () {
    $admin = User::factory()->administrator()->create();
    $receipt = PaymentReceipt::factory()->posted($admin)->create(['amount' => 50]);
    $distributor = Distributor::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('payment-receipts.store-update', $receipt), [
            'receipt_date' => '2026-08-08',
            'distributor_id' => $distributor->id,
            'payment_method' => PaymentMethod::Cash->value,
            'notes' => 'محاولة تعديل',
            'amount' => 10,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('payment-receipts.destroy', $receipt))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('payment-receipts.post', $receipt))
        ->assertForbidden();
});

test('sales role cannot manage payment receipts', function () {
    $user = User::factory()->create(['role' => UserRole::Sales]);

    $this->actingAs($user)
        ->get(route('payment-receipts.index'))
        ->assertForbidden();
});

test('administrator can view payment receipts index with receipt numbers', function () {
    $admin = User::factory()->administrator()->create();
    $receipt = PaymentReceipt::factory()->create();

    $this->actingAs($admin)
        ->get(route('payment-receipts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payment-receipts/index')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.id', $receipt->id)
            ->where('receipts.data.0.number', $receipt->number)
            ->where('filters.search', '')
            ->where('filters.status', '')
            ->where('filters.distributor_id', null)
            ->where('filters.payment_method', '')
            ->where('filters.from_date', null)
            ->where('filters.to_date', null)
            ->has('status_options')
            ->has('payment_method_options'));
});

test('payment receipts index can filter by distributor, payment method, status, and date range', function () {
    $admin = User::factory()->administrator()->create();
    $distributorA = Distributor::factory()->create();
    $distributorB = Distributor::factory()->create();

    $match = PaymentReceipt::factory()->create([
        'distributor_id' => $distributorA->id,
        'receipt_date' => '2026-08-10',
        'payment_method' => PaymentMethod::Fib,
        'status' => DocumentStatus::Draft,
    ]);
    PaymentReceipt::factory()->create([
        'distributor_id' => $distributorB->id,
        'receipt_date' => '2026-08-10',
        'payment_method' => PaymentMethod::Fib,
        'status' => DocumentStatus::Draft,
    ]);
    PaymentReceipt::factory()->create([
        'distributor_id' => $distributorA->id,
        'receipt_date' => '2026-07-01',
        'payment_method' => PaymentMethod::Fib,
        'status' => DocumentStatus::Draft,
    ]);
    PaymentReceipt::factory()->create([
        'distributor_id' => $distributorA->id,
        'receipt_date' => '2026-08-10',
        'payment_method' => PaymentMethod::Cash,
        'status' => DocumentStatus::Draft,
    ]);
    PaymentReceipt::factory()->posted($admin)->create([
        'distributor_id' => $distributorA->id,
        'receipt_date' => '2026-08-10',
        'payment_method' => PaymentMethod::Fib,
    ]);

    $this->actingAs($admin)
        ->get(route('payment-receipts.index', [
            'distributor_id' => $distributorA->id,
            'payment_method' => PaymentMethod::Fib->value,
            'status' => DocumentStatus::Draft->value,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('payment-receipts/index')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.id', $match->id)
            ->where('filters.distributor_id', $distributorA->id)
            ->where('filters.payment_method', PaymentMethod::Fib->value)
            ->where('filters.status', DocumentStatus::Draft->value)
            ->where('filters.from_date', '2026-08-01')
            ->where('filters.to_date', '2026-08-31')
            ->where('selected_distributor.value', $distributorA->id));
});

test('posted payment receipt can be printed and draft cannot', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-01',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => 1,
        'debit' => 200,
        'credit' => 0,
    ]);

    $posted = PaymentReceipt::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'payment_method' => PaymentMethod::Cash,
        'amount' => 75,
        'receipt_date' => '2026-08-07',
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-07',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => $posted->id,
        'debit' => 0,
        'credit' => 75,
    ]);

    $draft = PaymentReceipt::factory()->create();
    $cancelled = PaymentReceipt::factory()->cancelled($admin)->create();

    $this->actingAs($admin)
        ->get(route('payment-receipts.create-edit', $posted))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('payment-receipts/create-edit')
            ->where('can_print', true)
            ->where('can_cancel', true)
            ->where('can_edit', false)
            ->where('receipt.amount', '75')
            ->where('receipt.balance_before', '200 $')
            ->where('receipt.balance_after', '125 $')
            ->missing('selected_invoices'));

    $this->actingAs($admin)
        ->get(route('payment-receipts.print', $posted))
        ->assertSuccessful()
        ->assertSee($posted->number, false)
        ->assertSee('سند قبض', false)
        ->assertSee('الرقم', false)
        ->assertSee('التاريخ', false)
        ->assertSee('07/08/2026', false)
        ->assertSee('المبلغ', false)
        ->assertSee('مايعادل', false)
        ->assertSee('دينار', false)
        ->assertSee('نوع القبض', false)
        ->assertSee('ذمم العملاء', false)
        ->assertSee('الأسم', false)
        ->assertSee($distributor->name, false)
        ->assertSee('ر.سابق للزبون $', false)
        ->assertSee('المتبقي $', false)
        ->assertSee('ر.سابق دينار', false)
        ->assertSee('المتبقي دينار', false)
        ->assertSee('الملاحظات', false)
        ->assertDontSee('رقم الفاتورة', false)
        ->assertDontSee('إجمالي السند', false)
        ->assertSee('>200<', false)
        ->assertSee('>75<', false)
        ->assertSee('>125<', false)
        ->assertDontSee('75.00', false)
        ->assertDontSee('200 $', false)
        ->assertSee('المبلغ كتابةً', false)
        ->assertSee('خمسة وسبعون دولارا فقط', false)
        ->assertSee('/images/logo.png', false)
        ->assertSee('IBM Plex Sans Arabic', false)
        ->assertSee('/fonts/IBMPlexSansArabic-Regular.ttf', false)
        ->assertSee('window.print()', false);

    $this->actingAs($admin)
        ->get(route('payment-receipts.print', $draft))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('payment-receipts.print', $cancelled))
        ->assertForbidden();
});

test('posted payment receipt print uses uploaded logo when present', function () {
    Storage::fake('public');

    $admin = User::factory()->administrator()->create();

    $settings = SystemSetting::current();
    $path = UploadedFile::fake()->image('brand.png')->store('logos', 'public');
    $settings->update(['logo_path' => $path]);

    $posted = PaymentReceipt::factory()->posted($admin)->create(['amount' => 50]);

    $this->actingAs($admin)
        ->get(route('payment-receipts.print', $posted))
        ->assertSuccessful()
        ->assertSee(Storage::disk('public')->url($path), false)
        ->assertDontSee('/images/logo.png', false);
});

test('posted payment receipt print puts iqd amounts in the dinar boxes', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();

    SystemSetting::current()->update(['currency' => Currency::Iqd]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-01',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => 1,
        'debit' => 500,
        'credit' => 0,
    ]);

    $posted = PaymentReceipt::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'amount' => 80,
        'receipt_date' => '2026-09-15',
        'notes' => 'تسديد على الحساب',
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-09-15',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => $posted->id,
        'debit' => 0,
        'credit' => 80,
    ]);

    $this->actingAs($admin)
        ->get(route('payment-receipts.print', $posted))
        ->assertSuccessful()
        ->assertSee('15/09/2026', false)
        ->assertSee('تسديد على الحساب', false)
        ->assertSee('>80<', false)
        ->assertSee('>500<', false)
        ->assertSee('>420<', false)
        ->assertSee('المبلغ كتابةً', false)
        ->assertSee('دينار', false);
});
