<?php

use App\Enums\LedgerReferenceType;
use App\Enums\UserRole;
use App\Models\CustomerLedgerEntry;
use App\Models\Distributor;
use App\Models\OpeningBalance;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use App\Models\User;

test('accountant can view customer statement with running balance', function () {
    $user = User::factory()->create(['role' => UserRole::Accountant]);
    $distributor = Distributor::factory()->create(['name' => 'موزع الكشف']);

    $invoice = SalesInvoice::factory()->posted()->create([
        'distributor_id' => $distributor->id,
        'number' => 'INV-000111',
        'grand_total' => 200,
    ]);

    $receipt = PaymentReceipt::factory()->posted()->create([
        'distributor_id' => $distributor->id,
        'number' => 'REC-000222',
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-01',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $invoice->id,
        'debit' => 200,
        'credit' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-05',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => $receipt->id,
        'debit' => 0,
        'credit' => 50,
    ]);

    $this->actingAs($user)
        ->get(route('statements.index', [
            'distributor_id' => $distributor->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('statements/index')
            ->where('statement.distributor.name', 'موزع الكشف')
            ->where('statement.opening_balance', '0.00 $')
            ->where('statement.closing_balance', '150.00 $')
            ->where('statement.entries.0.reference_number', 'INV-000111')
            ->where('statement.entries.0.running_balance', '200.00 $')
            ->where('statement.entries.1.reference_number', 'REC-000222')
            ->where('statement.entries.1.running_balance', '150.00 $'));
});

test('statement opening balance includes activity before from date', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-07-15',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => 1,
        'debit' => 100,
        'credit' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-10',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => 2,
        'debit' => 0,
        'credit' => 40,
    ]);

    $this->actingAs($admin)
        ->get(route('statements.index', [
            'distributor_id' => $distributor->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('statement.opening_balance', '100.00 $')
            ->where('statement.closing_balance', '60.00 $')
            ->has('statement.entries', 1));
});

test('print and pdf endpoints return statement document', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-01',
        'debit' => 25,
        'credit' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('statements.print', ['distributor_id' => $distributor->id]))
        ->assertSuccessful()
        ->assertSee('كشف حساب العميل')
        ->assertSee($distributor->name);

    $this->actingAs($admin)
        ->get(route('statements.pdf', ['distributor_id' => $distributor->id]))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');

    $pdf = $this->actingAs($admin)
        ->get(route('statements.pdf', ['distributor_id' => $distributor->id]))
        ->getContent();

    expect($pdf)
        ->toStartWith('%PDF')
        ->toContain('IBMPlexSansArabic');
});

test('customer statement shows opening balance documents', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create(['name' => 'موزع الذمم السابقة']);

    $openingBalance = OpeningBalance::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'number' => 'OPB-000333',
        'amount' => 90,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-01-01',
        'reference_type' => LedgerReferenceType::OpeningBalance,
        'reference_id' => $openingBalance->id,
        'debit' => 90,
        'credit' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('statements.index', [
            'distributor_id' => $distributor->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('statement.entries.0.type', 'opening_balance')
            ->where('statement.entries.0.type_label', 'مبلغ غير مسدد')
            ->where('statement.entries.0.reference_number', 'OPB-000333')
            ->where('statement.closing_balance', '90.00 $'));
});

test('statement excludes cancelled sales invoices and payment receipts', function () {
    $admin = User::factory()->administrator()->create();
    $distributor = Distributor::factory()->create();

    $postedInvoice = SalesInvoice::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'number' => 'INV-000111',
        'grand_total' => 200,
    ]);

    $cancelledInvoice = SalesInvoice::factory()->cancelled($admin)->create([
        'distributor_id' => $distributor->id,
        'number' => 'INV-000999',
        'grand_total' => 300,
    ]);

    $postedReceipt = PaymentReceipt::factory()->posted($admin)->create([
        'distributor_id' => $distributor->id,
        'number' => 'REC-000222',
        'amount' => 50,
    ]);

    $cancelledReceipt = PaymentReceipt::factory()->cancelled($admin)->create([
        'distributor_id' => $distributor->id,
        'number' => 'REC-000888',
        'amount' => 80,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-07-15',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $cancelledInvoice->id,
        'debit' => 300,
        'credit' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-01',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $postedInvoice->id,
        'debit' => 200,
        'credit' => 0,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-02',
        'reference_type' => LedgerReferenceType::Invoice,
        'reference_id' => $cancelledInvoice->id,
        'debit' => 0,
        'credit' => 300,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-05',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => $postedReceipt->id,
        'debit' => 0,
        'credit' => 50,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-06',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => $cancelledReceipt->id,
        'debit' => 0,
        'credit' => 80,
    ]);

    CustomerLedgerEntry::factory()->create([
        'distributor_id' => $distributor->id,
        'entry_date' => '2026-08-06',
        'reference_type' => LedgerReferenceType::Receipt,
        'reference_id' => $cancelledReceipt->id,
        'debit' => 80,
        'credit' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('statements.index', [
            'distributor_id' => $distributor->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('statement.opening_balance', '0.00 $')
            ->where('statement.closing_balance', '150.00 $')
            ->has('statement.entries', 2)
            ->where('statement.entries.0.reference_number', 'INV-000111')
            ->where('statement.entries.1.reference_number', 'REC-000222'));
});

test('warehouse role cannot view statements', function () {
    $user = User::factory()->create(['role' => UserRole::Warehouse]);

    $this->actingAs($user)
        ->get(route('statements.index'))
        ->assertForbidden();
});
