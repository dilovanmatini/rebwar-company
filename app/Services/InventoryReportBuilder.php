<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ReportType;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Support\MoneyDisplay;
use Illuminate\Support\Collection;

class InventoryReportBuilder
{
    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     columns: list<array{key: string, label: string, numeric?: bool}>,
     *     groups: list<array{
     *         name: string,
     *         rows: list<array<string, string|null>>,
     *         totals: array<string, string|null>
     *     }>,
     *     grand_totals: array<string, string|null>,
     *     meta: array{row_count: int}
     * }
     */
    public function build(): array
    {
        $columns = $this->columns();
        $products = $this->products();

        $groups = $products
            ->groupBy(fn (Product $product): string => $product->category?->name ?: 'بدون مجموعة')
            ->map(fn (Collection $items, string $name): array => $this->group($name, $items))
            ->values();

        $grandTotals = $this->present($this->sumTotals(
            $groups->pluck('totals'),
            'الإجمالي',
        ));

        return [
            'type' => ReportType::Inventory->value,
            'title' => ReportType::Inventory->label(),
            'columns' => $columns,
            'groups' => $groups
                ->map(fn (array $group): array => [
                    'name' => $group['name'],
                    'rows' => array_map(fn (array $row): array => $this->present($row), $group['rows']),
                    'totals' => $this->present($group['totals']),
                ])
                ->all(),
            'grand_totals' => $grandTotals,
            'meta' => [
                'row_count' => $products->count(),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, numeric?: bool}>
     */
    private function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'وصف المنتج'],
            ['key' => 'quantity', 'label' => 'الكمية', 'numeric' => true],
            ['key' => 'unit', 'label' => 'الوحدة'],
            ['key' => 'unit_cost', 'label' => 'تكلفة الوحدة', 'numeric' => true],
            ['key' => 'total_cost', 'label' => 'إجمالي التكلفة', 'numeric' => true],
            ['key' => 'avg_cost', 'label' => 'متوسط التكلفة', 'numeric' => true],
            ['key' => 'total_avg_cost', 'label' => 'إجمالي المتوسط', 'numeric' => true],
            ['key' => 'unit_sp', 'label' => 'سعر البيع', 'numeric' => true],
            ['key' => 'total_sp', 'label' => 'إجمالي البيع', 'numeric' => true],
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        $lineTable = (new SalesInvoiceLine)->getTable();
        $invoiceTable = (new SalesInvoice)->getTable();
        $productTable = (new Product)->getTable();
        $categoryTable = (new Category)->getTable();

        return Product::query()
            ->with(['category:id,name', 'unit:id,name,symbol'])
            ->select($productTable.'.*')
            ->selectSub(
                InventoryTransaction::query()
                    ->selectRaw('COALESCE(SUM(quantity_in), 0) - COALESCE(SUM(quantity_out), 0)')
                    ->whereColumn('product_id', $productTable.'.id'),
                'available_quantity',
            )
            ->addSelect([
                'unit_selling_price' => SalesInvoiceLine::query()
                    ->select($lineTable.'.unit_price')
                    ->join($invoiceTable, $invoiceTable.'.id', '=', $lineTable.'.sales_invoice_id')
                    ->whereColumn($lineTable.'.product_id', $productTable.'.id')
                    ->where($invoiceTable.'.status', DocumentStatus::Posted)
                    ->orderByDesc($invoiceTable.'.invoice_date')
                    ->orderByDesc($lineTable.'.id')
                    ->limit(1),
            ])
            ->orderBy(
                Category::query()
                    ->select('name')
                    ->whereColumn($categoryTable.'.id', $productTable.'.category_id'),
            )
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{name: string, rows: list<array<string, string|null>>, totals: array<string, string|null>}
     */
    private function group(string $name, Collection $products): array
    {
        $rows = $products
            ->map(fn (Product $product): array => $this->row($product))
            ->values()
            ->all();

        return [
            'name' => $name,
            'rows' => $rows,
            'totals' => $this->sumTotals(collect($rows), 'المجموع حسب المجموعة'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function row(Product $product): array
    {
        $quantity = $this->numeric($product->available_quantity);
        $unitSp = $this->numeric($product->unit_selling_price);
        $zero = $this->numeric(0);

        return [
            'name' => $product->name_ar,
            'quantity' => $this->formatQuantity($quantity),
            'unit' => $product->unit?->name,
            'unit_cost' => $this->formatMoney($zero),
            'total_cost' => $this->formatMoney($zero),
            'avg_cost' => $this->formatMoney($zero),
            'total_avg_cost' => $this->formatMoney($zero),
            'unit_sp' => $this->formatMoney($unitSp),
            'total_sp' => $this->formatMoney(bcmul($quantity, $unitSp, 2)),
            '_quantity' => $quantity,
            '_total_sp' => bcmul($quantity, $unitSp, 2),
        ];
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $rows
     * @return array<string, string|null>
     */
    private function sumTotals(Collection $rows, string $label): array
    {
        $quantity = $rows->reduce(
            fn (string $carry, array $row): string => bcadd($carry, $row['_quantity'] ?? '0.00', 2),
            '0.00',
        );
        $totalSp = $rows->reduce(
            fn (string $carry, array $row): string => bcadd($carry, $row['_total_sp'] ?? '0.00', 2),
            '0.00',
        );
        $zero = $this->numeric(0);

        return [
            'name' => $label,
            'quantity' => $this->formatQuantity($quantity),
            'unit' => null,
            'unit_cost' => null,
            'total_cost' => $this->formatMoney($zero),
            'avg_cost' => null,
            'total_avg_cost' => $this->formatMoney($zero),
            'unit_sp' => null,
            'total_sp' => $this->formatMoney($totalSp),
            '_quantity' => $quantity,
            '_total_sp' => $totalSp,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private function present(array $row): array
    {
        return collect($row)->except(['_quantity', '_total_sp'])->all();
    }

    private function numeric(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function formatQuantity(string $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }

    private function formatMoney(string $value): string
    {
        return MoneyDisplay::format($value, thousands: true);
    }
}
