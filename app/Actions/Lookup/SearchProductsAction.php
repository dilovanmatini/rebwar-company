<?php

namespace App\Actions\Lookup;

use App\Actions\Lookup\Concerns\ResolvesLookupSearch;
use App\Enums\DocumentStatus;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Support\QuantityDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchProductsAction
{
    use ResolvesLookupSearch;

    public function handle(Request $request): JsonResponse
    {
        $search = $this->searchTerm($request);
        $limit = $this->resultLimit($request);
        $includeIds = $this->includeIds($request);
        $productTable = (new Product)->getTable();
        $lineTable = (new SalesInvoiceLine)->getTable();
        $invoiceTable = (new SalesInvoice)->getTable();

        $products = Product::query()
            ->with(['unit:id,name,symbol'])
            ->where(function ($query) use ($includeIds): void {
                $query->where('is_active', true);

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name_ar', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->select([
                "{$productTable}.id",
                "{$productTable}.code",
                "{$productTable}.barcode",
                "{$productTable}.name_ar",
                "{$productTable}.unit_id",
            ])
            ->addSelect([
                'last_unit_price' => SalesInvoiceLine::query()
                    ->select("{$lineTable}.unit_price")
                    ->join($invoiceTable, "{$invoiceTable}.id", '=', "{$lineTable}.sales_invoice_id")
                    ->whereColumn("{$lineTable}.product_id", "{$productTable}.id")
                    ->where("{$invoiceTable}.status", DocumentStatus::Posted)
                    ->orderByDesc("{$invoiceTable}.invoice_date")
                    ->orderByDesc("{$lineTable}.id")
                    ->limit(1),
            ])
            ->orderBy('name_ar')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'value' => $product->id,
                'label' => $product->selectionLabel(),
                'meta' => [
                    'code' => $product->code,
                    'barcode' => $product->barcode,
                    'name_ar' => $product->name_ar,
                    'unit' => $product->unit?->name,
                    'last_unit_price' => $product->last_unit_price === null
                        ? null
                        : QuantityDisplay::format($product->last_unit_price, 2),
                ],
            ])->values()->all(),
        ]);
    }
}
