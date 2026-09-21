<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ReportType;
use App\Models\SalesInvoice;
use App\Support\MoneyDisplay;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SalesByProductReportBuilder
{
    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     from_date: string|null,
     *     to_date: string|null,
     *     salesmen: list<array{
     *         id: int|string,
     *         name: string,
     *         quantity: string,
     *         total: string,
     *         percent: string,
     *         customers: list<array{
     *             id: int,
     *             name: string,
     *             quantity: string,
     *             total: string,
     *             percent: string,
     *             products: list<array{
     *                 id: int,
     *                 name: string,
     *                 quantity: string,
     *                 total: string,
     *                 percent: string
     *             }>
     *         }>
     *     }>,
     *     totals: array{quantity: string, total: string, percent: string}
     * }
     */
    public function build(?CarbonInterface $fromDate = null, ?CarbonInterface $toDate = null): array
    {
        $lines = $this->aggregatedLines($fromDate, $toDate);
        $grandTotal = $this->sum($lines, 'total');
        $grandQuantity = $this->sum($lines, 'quantity');

        $salesmen = $lines
            ->groupBy(fn (object $row): string => (string) ($row->salesman_id ?? '0'))
            ->map(function (Collection $salesmanLines) use ($grandTotal): array {
                $salesmanTotal = $this->sum($salesmanLines, 'total');
                $salesmanQuantity = $this->sum($salesmanLines, 'quantity');
                $salesmanName = filled($salesmanLines->first()->salesman_name)
                    ? (string) $salesmanLines->first()->salesman_name
                    : 'غير محدد';

                $customers = $salesmanLines
                    ->groupBy(fn (object $row): string => (string) $row->distributor_id)
                    ->map(function (Collection $customerLines) use ($grandTotal): array {
                        $customerTotal = $this->sum($customerLines, 'total');
                        $customerQuantity = $this->sum($customerLines, 'quantity');

                        return [
                            'id' => (int) $customerLines->first()->distributor_id,
                            'name' => (string) $customerLines->first()->customer_name,
                            'quantity' => $this->quantity($customerQuantity),
                            'total' => $this->money($customerTotal),
                            'percent' => $this->percent($customerTotal, $grandTotal),
                            'products' => $customerLines->map(fn (object $row): array => [
                                'id' => (int) $row->product_id,
                                'name' => (string) $row->product_name,
                                'quantity' => $this->quantity($row->quantity),
                                'total' => $this->money($row->total),
                                'percent' => $this->percent($this->decimal($row->total), $grandTotal),
                            ])->values()->all(),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $salesmanLines->first()->salesman_id ?? '0',
                    'name' => $salesmanName,
                    'quantity' => $this->quantity($salesmanQuantity),
                    'total' => $this->money($salesmanTotal),
                    'percent' => $this->percent($salesmanTotal, $grandTotal),
                    'customers' => $customers,
                ];
            })
            ->values()
            ->all();

        return [
            'type' => ReportType::Sales->value,
            'title' => ReportType::Sales->label(),
            'subtitle' => 'المبيعات حسب المندوب حسب العميل حسب المنتج',
            'from_date' => $fromDate?->toDateString(),
            'to_date' => $toDate?->toDateString(),
            'salesmen' => $salesmen,
            'totals' => [
                'quantity' => $this->quantity($grandQuantity),
                'total' => $this->money($grandTotal),
                'percent' => bccomp($grandTotal, '0.00', 2) === 0 ? '0.00' : '100.00',
            ],
        ];
    }

    /**
     * @return Collection<int, object{
     *     salesman_id: mixed,
     *     salesman_name: mixed,
     *     distributor_id: mixed,
     *     customer_name: mixed,
     *     product_id: mixed,
     *     product_name: mixed,
     *     quantity: mixed,
     *     total: mixed
     * }>
     */
    private function aggregatedLines(?CarbonInterface $fromDate, ?CarbonInterface $toDate): Collection
    {
        return SalesInvoice::query()
            ->join('sales_invoice_lines', 'sales_invoice_lines.sales_invoice_id', '=', 'sales_invoices.id')
            ->join('products', 'products.id', '=', 'sales_invoice_lines.product_id')
            ->join('distributors', 'distributors.id', '=', 'sales_invoices.distributor_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'sales_invoices.created_by')
            ->leftJoin('users as posters', 'posters.id', '=', 'sales_invoices.posted_by')
            ->where('sales_invoices.status', DocumentStatus::Posted)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '<=', $toDate))
            ->groupByRaw('COALESCE(sales_invoices.created_by, sales_invoices.posted_by)')
            ->groupByRaw('COALESCE(creators.name, posters.name)')
            ->groupBy('sales_invoices.distributor_id', 'distributors.name', 'sales_invoice_lines.product_id', 'products.name_ar')
            ->orderByRaw('COALESCE(creators.name, posters.name)')
            ->orderBy('distributors.name')
            ->orderBy('products.name_ar')
            ->select([
                'sales_invoices.distributor_id',
                'distributors.name as customer_name',
                'sales_invoice_lines.product_id',
                'products.name_ar as product_name',
            ])
            ->selectRaw('COALESCE(sales_invoices.created_by, sales_invoices.posted_by) as salesman_id')
            ->selectRaw('COALESCE(creators.name, posters.name) as salesman_name')
            ->selectRaw('SUM(sales_invoice_lines.quantity) as quantity')
            ->selectRaw('SUM(sales_invoice_lines.line_total) as total')
            ->toBase()
            ->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function sum(Collection $rows, string $attribute): string
    {
        return $rows->reduce(
            fn (string $carry, object $row): string => bcadd($carry, $this->decimal($row->{$attribute}), 2),
            '0.00',
        );
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', ',');
    }

    private function money(mixed $value): string
    {
        return MoneyDisplay::format($value, thousands: true);
    }

    private function percent(string $amount, string $grandTotal): string
    {
        if (bccomp($grandTotal, '0.00', 2) === 0) {
            return '0.00';
        }

        return number_format((float) bcmul(bcdiv($amount, $grandTotal, 8), '100', 4), 2, '.', '');
    }
}
