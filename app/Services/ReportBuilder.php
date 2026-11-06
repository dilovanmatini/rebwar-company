<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ReportType;
use App\Models\Distributor;
use App\Models\InventoryTransaction;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Support\MoneyDisplay;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReportBuilder
{
    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     description: string,
     *     from_date: string|null,
     *     to_date: string|null,
     *     columns: list<array{key: string, label: string}>,
     *     rows: list<array<string, string|null>>,
     *     meta: array{row_count: int}
     * }
     */
    public function build(
        ReportType $type,
        ?CarbonInterface $fromDate = null,
        ?CarbonInterface $toDate = null,
    ): array {
        [$columns, $rows] = match ($type) {
            ReportType::Inventory => $this->inventory(),
            ReportType::Suppliers => $this->suppliers(),
            ReportType::Purchases => $this->purchases($fromDate, $toDate),
            ReportType::Sales => $this->sales($fromDate, $toDate),
            ReportType::OutstandingCustomers => $this->outstandingCustomers(),
            ReportType::Payments => $this->payments($fromDate, $toDate),
            ReportType::DailySales => $this->dailySales($fromDate, $toDate),
            ReportType::MonthlySales => $this->monthlySales($fromDate, $toDate),
        };

        return [
            'type' => $type->value,
            'title' => $type->label(),
            'description' => $type->description(),
            'from_date' => $fromDate?->toDateString(),
            'to_date' => $toDate?->toDateString(),
            'columns' => $columns,
            'rows' => $rows,
            'meta' => ['row_count' => count($rows)],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function inventory(): array
    {
        $columns = [
            ['key' => 'code', 'label' => 'الرمز'],
            ['key' => 'name_ar', 'label' => 'المنتج'],
            ['key' => 'category', 'label' => 'گروپ'],
            ['key' => 'unit', 'label' => 'الوحدة'],
            ['key' => 'available_quantity', 'label' => 'الكمية المتاحة'],
        ];

        $rows = Product::query()
            ->with(['category:id,name', 'unit:id,name,symbol'])
            ->select('products.*')
            ->selectSub(
                InventoryTransaction::query()
                    ->selectRaw('COALESCE(SUM(quantity_in), 0) - COALESCE(SUM(quantity_out), 0)')
                    ->whereColumn('product_id', 'products.id'),
                'available_quantity',
            )
            ->orderBy('name_ar')
            ->get()
            ->map(fn (Product $product): array => [
                'code' => $product->code,
                'name_ar' => $product->name_ar,
                'category' => $product->category?->name,
                'unit' => $product->unit
                    ? ($product->unit->symbol
                        ? "{$product->unit->name} ({$product->unit->symbol})"
                        : $product->unit->name)
                    : null,
                'available_quantity' => number_format((float) ($product->available_quantity ?? 0), 3, '.', ''),
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function suppliers(): array
    {
        $columns = [
            ['key' => 'name', 'label' => 'المورد'],
            ['key' => 'contact_person', 'label' => 'جهة الاتصال'],
            ['key' => 'phone', 'label' => 'الهاتف'],
            ['key' => 'purchases_count', 'label' => 'عدد المشتريات'],
            ['key' => 'is_active', 'label' => 'الحالة'],
        ];

        $rows = Supplier::query()
            ->withCount('purchases')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone' => $supplier->phone,
                'purchases_count' => (string) $supplier->purchases_count,
                'is_active' => $supplier->is_active ? 'نشط' : 'غير نشط',
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function purchases(?CarbonInterface $fromDate, ?CarbonInterface $toDate): array
    {
        $columns = [
            ['key' => 'number', 'label' => 'الرقم'],
            ['key' => 'receipt_number', 'label' => 'رقم الوصل'],
            ['key' => 'purchase_date', 'label' => 'التاريخ'],
            ['key' => 'supplier', 'label' => 'المورد'],
            ['key' => 'lines_count', 'label' => 'عدد العناصر'],
            ['key' => 'status', 'label' => 'الحالة'],
        ];

        $rows = Purchase::query()
            ->with('supplier:id,name')
            ->withCount('lines')
            ->where('status', DocumentStatus::Posted)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('purchase_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('purchase_date', '<=', $toDate))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'number' => $purchase->number,
                'receipt_number' => $purchase->receipt_number,
                'purchase_date' => $purchase->purchase_date?->toDateString(),
                'supplier' => $purchase->supplier?->name,
                'lines_count' => (string) $purchase->lines_count,
                'status' => $purchase->status->label(),
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function sales(?CarbonInterface $fromDate, ?CarbonInterface $toDate): array
    {
        $columns = [
            ['key' => 'number', 'label' => 'الرقم'],
            ['key' => 'invoice_date', 'label' => 'التاريخ'],
            ['key' => 'distributor', 'label' => 'الموزع'],
            ['key' => 'subtotal', 'label' => 'المجموع'],
            ['key' => 'discount', 'label' => 'الخصم'],
            ['key' => 'grand_total', 'label' => 'الإجمالي'],
        ];

        $rows = SalesInvoice::query()
            ->with('distributor:id,name')
            ->where('status', DocumentStatus::Posted)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('invoice_date', '<=', $toDate))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SalesInvoice $invoice): array => [
                'number' => $invoice->number,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'distributor' => $invoice->distributor?->name,
                'subtotal' => MoneyDisplay::format($invoice->subtotal),
                'discount' => MoneyDisplay::format($invoice->discount),
                'grand_total' => MoneyDisplay::format($invoice->grand_total),
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function outstandingCustomers(): array
    {
        $columns = [
            ['key' => 'name', 'label' => 'الموزع'],
            ['key' => 'phone', 'label' => 'الهاتف'],
            ['key' => 'balance', 'label' => 'الرصيد المتبقي'],
        ];

        $balances = DB::table('customer_ledger_entries')
            ->select('distributor_id')
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->groupBy('distributor_id')
            ->havingRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) > 0')
            ->pluck('balance', 'distributor_id');

        $rows = Distributor::query()
            ->whereIn('id', $balances->keys())
            ->orderBy('name')
            ->get()
            ->map(fn (Distributor $distributor): array => [
                'name' => $distributor->name,
                'phone' => $distributor->phone,
                'balance' => MoneyDisplay::format($balances[$distributor->id]),
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function payments(?CarbonInterface $fromDate, ?CarbonInterface $toDate): array
    {
        $columns = [
            ['key' => 'number', 'label' => 'الرقم'],
            ['key' => 'receipt_date', 'label' => 'التاريخ'],
            ['key' => 'distributor', 'label' => 'الموزع'],
            ['key' => 'payment_method', 'label' => 'طريقة الدفع'],
            ['key' => 'total_amount', 'label' => 'المبلغ'],
        ];

        $rows = PaymentReceipt::query()
            ->with('distributor:id,name')
            ->where('status', DocumentStatus::Posted)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('receipt_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('receipt_date', '<=', $toDate))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PaymentReceipt $receipt): array => [
                'number' => $receipt->number,
                'receipt_date' => $receipt->receipt_date?->toDateString(),
                'distributor' => $receipt->distributor?->name,
                'payment_method' => $receipt->payment_method->label(),
                'total_amount' => MoneyDisplay::format($receipt->amount ?? 0),
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function dailySales(?CarbonInterface $fromDate, ?CarbonInterface $toDate): array
    {
        $columns = [
            ['key' => 'sale_date', 'label' => 'التاريخ'],
            ['key' => 'invoices_count', 'label' => 'عدد الفواتير'],
            ['key' => 'total_sales', 'label' => 'إجمالي المبيعات'],
        ];

        $rows = SalesInvoice::query()
            ->selectRaw('invoice_date as sale_date')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_sales')
            ->where('status', DocumentStatus::Posted)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('invoice_date', '<=', $toDate))
            ->groupBy('invoice_date')
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn ($row): array => [
                'sale_date' => (string) $row->sale_date,
                'invoices_count' => (string) $row->invoices_count,
                'total_sales' => MoneyDisplay::format($row->total_sales),
            ])
            ->all();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, string|null>>}
     */
    private function monthlySales(?CarbonInterface $fromDate, ?CarbonInterface $toDate): array
    {
        $columns = [
            ['key' => 'sale_month', 'label' => 'الشهر'],
            ['key' => 'invoices_count', 'label' => 'عدد الفواتير'],
            ['key' => 'total_sales', 'label' => 'إجمالي المبيعات'],
        ];

        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', invoice_date)"
            : "DATE_FORMAT(invoice_date, '%Y-%m')";

        $rows = SalesInvoice::query()
            ->selectRaw("{$monthExpression} as sale_month")
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_sales')
            ->where('status', DocumentStatus::Posted)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('invoice_date', '<=', $toDate))
            ->groupBy('sale_month')
            ->orderByDesc('sale_month')
            ->get()
            ->map(fn ($row): array => [
                'sale_month' => (string) $row->sale_month,
                'invoices_count' => (string) $row->invoices_count,
                'total_sales' => MoneyDisplay::format($row->total_sales),
            ])
            ->all();

        return [$columns, $rows];
    }
}
