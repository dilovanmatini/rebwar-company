<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\Currency;
use App\Models\PaymentReceipt;
use App\Models\SystemSetting;
use App\Support\ArabicMoneyWords;
use App\Support\PaymentReceiptBalanceSnapshot;
use Illuminate\Http\Response;

class PrintAction
{
    public function __construct(private PaymentReceiptBalanceSnapshot $snapshot) {}

    public function handle(PaymentReceipt $paymentReceipt): Response
    {
        $paymentReceipt->load('distributor');

        $settings = SystemSetting::current();
        $snapshot = $this->snapshot->forPosted($paymentReceipt);
        $currency = $settings->currency instanceof Currency ? $settings->currency : Currency::Usd;
        $isUsd = $currency === Currency::Usd;
        $zero = '0';

        $amount = $this->numeric($snapshot['amount']);
        $before = $this->numeric($snapshot['before']);
        $after = $this->numeric($snapshot['after']);

        return response()->view('payment-receipts.print', [
            'receipt' => [
                'number' => $paymentReceipt->number,
                'receipt_date' => $paymentReceipt->receipt_date?->format('d/m/Y') ?: '—',
                'notes' => $paymentReceipt->notes,
                'receipt_type' => 'ذمم العملاء',
                'total_amount_in_words' => ArabicMoneyWords::phrase($snapshot['amount']),
                'distributor_name' => $paymentReceipt->distributor?->name ?? '—',
                'usd' => [
                    'amount' => $isUsd ? $amount : $zero,
                    'equivalent' => $zero,
                    'before' => $isUsd ? $before : $zero,
                    'after' => $isUsd ? $after : $zero,
                ],
                'iqd' => [
                    'amount' => $isUsd ? $zero : $amount,
                    'equivalent' => $zero,
                    'before' => $isUsd ? $zero : $before,
                    'after' => $isUsd ? $zero : $after,
                ],
            ],
            'app_name' => $settings->app_name,
            'logo_url' => $settings->displayLogoUrl(),
            'invoice_header' => $settings->receipt_header,
            'invoice_footer' => $settings->receipt_footer,
            'back_url' => route('payment-receipts.create-edit', $paymentReceipt),
        ]);
    }

    private function numeric(mixed $value): string
    {
        $amount = number_format((float) ($value ?? 0), 2, '.', '');

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
