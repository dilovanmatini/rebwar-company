<?php

namespace App\Services;

use App\Enums\LedgerReferenceType;
use App\Models\CustomerLedgerEntry;
use App\Models\Distributor;
use App\Models\OpeningBalance;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use App\Support\MoneyDisplay;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CustomerStatementBuilder
{
    /**
     * @return array{
     *     distributor: array{id: int, name: string, contact_person: string|null, phone: string|null, address: string|null},
     *     from_date: string|null,
     *     to_date: string|null,
     *     opening_balance: string,
     *     closing_balance: string,
     *     total_debit: string,
     *     total_credit: string,
     *     entries: list<array{
     *         id: int,
     *         entry_date: string,
     *         type: string,
     *         type_label: string,
     *         reference_number: string|null,
     *         debit: string,
     *         credit: string,
     *         running_balance: string
     *     }>
     * }
     */
    public function build(
        Distributor $distributor,
        ?CarbonInterface $fromDate = null,
        ?CarbonInterface $toDate = null,
    ): array {
        $openingBalance = $this->balanceBefore($distributor->id, $fromDate);

        $entries = CustomerLedgerEntry::query()
            ->withoutCancelledDocuments()
            ->where('distributor_id', $distributor->id)
            ->when($fromDate !== null, fn ($query) => $query->whereDate('entry_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('entry_date', '<=', $toDate))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $referenceNumbers = $this->referenceNumbers($entries);

        $running = $openingBalance;
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        $rows = [];

        foreach ($entries as $entry) {
            $debit = number_format((float) $entry->debit, 2, '.', '');
            $credit = number_format((float) $entry->credit, 2, '.', '');

            $running = bcadd($running, $debit, 2);
            $running = bcsub($running, $credit, 2);

            $totalDebit = bcadd($totalDebit, $debit, 2);
            $totalCredit = bcadd($totalCredit, $credit, 2);

            $rows[] = [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date?->toDateString() ?? '',
                'type' => $entry->reference_type->value,
                'type_label' => $entry->reference_type->label(),
                'reference_number' => $referenceNumbers[$entry->reference_type->value][$entry->reference_id] ?? null,
                'debit' => MoneyDisplay::withSymbol($debit),
                'credit' => MoneyDisplay::withSymbol($credit),
                'running_balance' => MoneyDisplay::withSymbol($running),
            ];
        }

        return [
            'distributor' => [
                'id' => $distributor->id,
                'name' => $distributor->name,
                'contact_person' => $distributor->contact_person,
                'phone' => $distributor->phone,
                'address' => $distributor->address,
            ],
            'from_date' => $fromDate?->toDateString(),
            'to_date' => $toDate?->toDateString(),
            'opening_balance' => MoneyDisplay::withSymbol($openingBalance),
            'closing_balance' => MoneyDisplay::withSymbol($running),
            'total_debit' => MoneyDisplay::withSymbol($totalDebit),
            'total_credit' => MoneyDisplay::withSymbol($totalCredit),
            'entries' => $rows,
        ];
    }

    private function balanceBefore(int $distributorId, ?CarbonInterface $fromDate): string
    {
        if ($fromDate === null) {
            return '0.00';
        }

        $result = CustomerLedgerEntry::query()
            ->withoutCancelledDocuments()
            ->where('distributor_id', $distributorId)
            ->whereDate('entry_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');

        return number_format((float) ($result ?? 0), 2, '.', '');
    }

    /**
     * @param  Collection<int, CustomerLedgerEntry>  $entries
     * @return array<string, array<int, string>>
     */
    private function referenceNumbers(Collection $entries): array
    {
        $invoiceIds = $entries
            ->where('reference_type', LedgerReferenceType::Invoice)
            ->pluck('reference_id')
            ->unique()
            ->all();

        $receiptIds = $entries
            ->where('reference_type', LedgerReferenceType::Receipt)
            ->pluck('reference_id')
            ->unique()
            ->all();

        $openingBalanceIds = $entries
            ->where('reference_type', LedgerReferenceType::OpeningBalance)
            ->pluck('reference_id')
            ->unique()
            ->all();

        return [
            LedgerReferenceType::Invoice->value => SalesInvoice::query()
                ->whereIn('id', $invoiceIds)
                ->pluck('number', 'id')
                ->all(),
            LedgerReferenceType::Receipt->value => PaymentReceipt::query()
                ->whereIn('id', $receiptIds)
                ->pluck('number', 'id')
                ->all(),
            LedgerReferenceType::OpeningBalance->value => OpeningBalance::query()
                ->whereIn('id', $openingBalanceIds)
                ->pluck('number', 'id')
                ->all(),
        ];
    }
}
