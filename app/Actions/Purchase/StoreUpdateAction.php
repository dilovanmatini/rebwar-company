<?php

namespace App\Actions\Purchase;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Requests\StoreUpdatePurchaseRequest;
use App\Models\Purchase;
use App\Services\DocumentNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StoreUpdateAction
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    public function handle(StoreUpdatePurchaseRequest $request, ?Purchase $purchase): RedirectResponse
    {
        if ($purchase?->exists) {
            DB::transaction(function () use ($request, $purchase): void {
                $data = $request->validated();

                $purchase->update([
                    'purchase_date' => $data['purchase_date'],
                    'receipt_number' => $data['receipt_number'],
                    'supplier_id' => $data['supplier_id'],
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->syncLines($purchase, $data['lines']);
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث المشترى بنجاح.']);

            return to_route('purchases.create-edit', $purchase);
        }

        $purchase = DB::transaction(function () use ($request): Purchase {
            $data = $request->validated();

            $purchase = Purchase::query()->create([
                'number' => $this->numbers->generate(DocumentType::Purchase),
                'receipt_number' => $data['receipt_number'],
                'purchase_date' => $data['purchase_date'],
                'supplier_id' => $data['supplier_id'],
                'notes' => $data['notes'] ?? null,
                'status' => DocumentStatus::Draft,
            ]);

            $this->syncLines($purchase, $data['lines']);

            return $purchase;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إنشاء المشترى بنجاح.']);

        return to_route('purchases.create-edit', $purchase);
    }

    /**
     * @param  list<array{product_id: int, quantity: numeric-string|float|int}>  $lines
     */
    private function syncLines(Purchase $purchase, array $lines): void
    {
        $purchase->lines()->delete();

        foreach ($lines as $line) {
            $purchase->lines()->create([
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
            ]);
        }
    }
}
