<?php

namespace App\Actions\Purchase;

use App\Actions\Concerns\FiltersDocumentIndex;
use App\Actions\Concerns\ResolvesDatagridPerPage;
use App\Enums\DocumentStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexAction
{
    use FiltersDocumentIndex;
    use ResolvesDatagridPerPage;

    public function handle(Request $request): Response
    {
        $filters = $this->documentIndexFilters($request, 'supplier_id', 'suppliers');

        $purchases = Purchase::query()
            ->with(['supplier:id,name'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                $filters['status'] !== '',
                fn (Builder $query) => $query->where('status', $filters['status']),
                fn (Builder $query) => $query->where('status', '!=', DocumentStatus::Cancelled),
            )
            ->when(
                $filters['party_id'] !== null,
                fn (Builder $query) => $query->where('supplier_id', $filters['party_id']),
            )
            ->when(
                $filters['from_date'] !== null,
                fn (Builder $query) => $query->whereDate('purchase_date', '>=', $filters['from_date']),
            )
            ->when(
                $filters['to_date'] !== null,
                fn (Builder $query) => $query->whereDate('purchase_date', '<=', $filters['to_date']),
            )
            ->latest('id')
            ->paginate($this->perPage($request, 'purchases'))
            ->withQueryString()
            ->through(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'receipt_number' => $purchase->receipt_number,
                'purchase_date' => $purchase->purchase_date?->toDateString(),
                'supplier' => $purchase->supplier?->only(['id', 'name']),
                'status' => $purchase->status->value,
                'status_label' => $purchase->status->label(),
                'is_posted' => $purchase->isPosted(),
                'can_edit' => $purchase->isDraft(),
                'can_delete' => $purchase->isDraft(),
                'can_cancel' => $purchase->isPosted(),
            ]);

        $selectedSupplier = null;

        if ($filters['party_id'] !== null) {
            $supplier = Supplier::query()->find($filters['party_id']);

            if ($supplier !== null) {
                $selectedSupplier = [
                    'value' => $supplier->id,
                    'label' => $supplier->name,
                ];
            }
        }

        return Inertia::render('purchases/index', [
            'purchases' => $purchases,
            'selected_supplier' => $selectedSupplier,
            'filters' => [
                'search' => $filters['search'],
                'status' => $filters['status'],
                'supplier_id' => $filters['party_id'],
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
            ],
            'status_options' => $this->documentStatusOptions(),
        ]);
    }
}
