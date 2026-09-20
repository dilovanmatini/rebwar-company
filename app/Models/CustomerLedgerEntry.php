<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\LedgerReferenceType;
use App\Models\Concerns\Auditable;
use Database\Factories\CustomerLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'entry_date',
    'reference_type',
    'reference_id',
    'debit',
    'credit',
])]
class CustomerLedgerEntry extends Model
{
    /** @use HasFactory<CustomerLedgerEntryFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'reference_type' => LedgerReferenceType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @param  Builder<CustomerLedgerEntry>  $query
     */
    #[Scope]
    protected function withoutCancelledDocuments(Builder $query): void
    {
        $query
            ->whereNot(function (Builder $query): void {
                $query->where('reference_type', LedgerReferenceType::Invoice)
                    ->whereIn(
                        'reference_id',
                        SalesInvoice::query()
                            ->where('status', DocumentStatus::Cancelled)
                            ->select('id'),
                    );
            })
            ->whereNot(function (Builder $query): void {
                $query->where('reference_type', LedgerReferenceType::Receipt)
                    ->whereIn(
                        'reference_id',
                        PaymentReceipt::query()
                            ->where('status', DocumentStatus::Cancelled)
                            ->select('id'),
                    );
            });
    }
}
