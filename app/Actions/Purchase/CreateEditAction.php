<?php

namespace App\Actions\Purchase;

use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Support\QuantityDisplay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreateEditAction
{
    public function handle(Request $request, ?Purchase $purchase): Response
    {
        if ($purchase?->exists) {
            $purchase->load(['lines.product:id,code,name_ar', 'supplier:id,name']);

            return Inertia::render('purchases/create-edit', [
                'purchase' => [
                    'id' => $purchase->id,
                    'number' => $purchase->number,
                    'receipt_number' => $purchase->receipt_number,
                    'purchase_date' => $purchase->purchase_date?->toDateString(),
                    'supplier_id' => $purchase->supplier_id,
                    'notes' => $purchase->notes,
                    'status' => $purchase->status->value,
                    'status_label' => $purchase->status->label(),
                    'is_posted' => $purchase->isPosted(),
                    'posted_at' => $purchase->posted_at?->toIso8601String(),
                    'lines' => $purchase->lines->map(fn (PurchaseLine $line): array => [
                        'product_id' => $line->product_id,
                        'quantity' => QuantityDisplay::format($line->quantity),
                        'product' => $line->product?->only(['id', 'code', 'name_ar']),
                    ])->values()->all(),
                ],
                'selected_supplier' => $purchase->supplier
                    ? [
                        'value' => $purchase->supplier->id,
                        'label' => $purchase->supplier->name,
                    ]
                    : null,
                'selected_products' => $purchase->lines
                    ->map(function (PurchaseLine $line): ?array {
                        if ($line->product === null) {
                            return null;
                        }

                        return [
                            'value' => $line->product->id,
                            'label' => $line->product->selectionLabel(),
                        ];
                    })
                    ->filter()
                    ->unique('value')
                    ->values()
                    ->all(),
                'can_edit' => $purchase->isDraft(),
                'can_post' => $purchase->isDraft() && $purchase->lines->isNotEmpty(),
                'can_cancel' => $purchase->isPosted(),
            ]);
        }

        return Inertia::render('purchases/create-edit', [
            'purchase' => null,
            'selected_supplier' => null,
            'selected_products' => [],
            'can_edit' => true,
            'can_post' => false,
            'can_cancel' => false,
        ]);
    }
}
