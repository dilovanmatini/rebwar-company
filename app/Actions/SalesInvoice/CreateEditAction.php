<?php

namespace App\Actions\SalesInvoice;

use App\Models\Distributor;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Support\QuantityDisplay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreateEditAction
{
    public function handle(Request $request, ?SalesInvoice $salesInvoice): Response
    {
        if ($salesInvoice?->exists) {
            $salesInvoice->load(['lines.product:id,code,barcode,name_ar', 'distributor:id,name']);

            return Inertia::render('sales-invoices/create-edit', [
                'invoice' => [
                    'id' => $salesInvoice->id,
                    'number' => $salesInvoice->number,
                    'invoice_date' => $salesInvoice->invoice_date?->toDateString(),
                    'distributor_id' => $salesInvoice->distributor_id,
                    'notes' => $salesInvoice->notes,
                    'subtotal' => QuantityDisplay::format($salesInvoice->subtotal, 2),
                    'discount' => QuantityDisplay::format($salesInvoice->discount, 2),
                    'grand_total' => QuantityDisplay::format($salesInvoice->grand_total, 2),
                    'status' => $salesInvoice->status->value,
                    'status_label' => $salesInvoice->status->label(),
                    'is_posted' => $salesInvoice->isPosted(),
                    'posted_at' => $salesInvoice->posted_at?->toIso8601String(),
                    'lines' => $salesInvoice->lines->map(fn (SalesInvoiceLine $line): array => [
                        'product_id' => $line->product_id,
                        'quantity' => QuantityDisplay::format($line->quantity),
                        'unit_price' => QuantityDisplay::format($line->unit_price, 2),
                        'line_total' => QuantityDisplay::format($line->line_total, 2),
                        'product' => $line->product?->only(['id', 'code', 'barcode', 'name_ar']),
                    ])->values()->all(),
                ],
                'selected_distributor' => $salesInvoice->distributor
                    ? [
                        'value' => $salesInvoice->distributor->id,
                        'label' => $salesInvoice->distributor->name,
                    ]
                    : null,
                'selected_products' => $salesInvoice->lines
                    ->map(function (SalesInvoiceLine $line): ?array {
                        if ($line->product === null) {
                            return null;
                        }

                        return [
                            'value' => $line->product->id,
                            'label' => $line->product->selectionLabel(),
                            'meta' => [
                                'code' => $line->product->code,
                                'barcode' => $line->product->barcode,
                                'name_ar' => $line->product->name_ar,
                            ],
                        ];
                    })
                    ->filter()
                    ->unique('value')
                    ->values()
                    ->all(),
                'can_edit' => $salesInvoice->isDraft(),
                'can_post' => $salesInvoice->isDraft() && $salesInvoice->lines->isNotEmpty(),
                'can_cancel' => $salesInvoice->isPosted(),
                'can_print' => $salesInvoice->isPosted(),
            ]);
        }

        return Inertia::render('sales-invoices/create-edit', [
            'invoice' => null,
            'selected_distributor' => $this->selectedDistributorFromRequest($request),
            'selected_products' => [],
            'can_edit' => true,
            'can_post' => false,
            'can_cancel' => false,
            'can_print' => false,
        ]);
    }

    /**
     * @return array{value: int, label: string}|null
     */
    private function selectedDistributorFromRequest(Request $request): ?array
    {
        if (! $request->filled('distributor_id')) {
            return null;
        }

        $distributor = Distributor::query()
            ->whereKey($request->integer('distributor_id'))
            ->first(['id', 'name']);

        if ($distributor === null) {
            return null;
        }

        return [
            'value' => $distributor->id,
            'label' => $distributor->name,
        ];
    }
}
