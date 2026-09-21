<?php

namespace App\Actions\SalesInvoice;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SystemSetting;
use App\Support\QuantityDisplay;
use Illuminate\Http\Response;

class PrintAction
{
    public function handle(SalesInvoice $salesInvoice): Response
    {
        $salesInvoice->load(['lines.product:id,code,name_ar', 'distributor']);

        $settings = SystemSetting::current();

        return response()->view('sales-invoices.print', [
            'invoice' => [
                'number' => $salesInvoice->number,
                'invoice_date' => $salesInvoice->invoice_date?->format('d-m-Y') ?? '—',
                'notes' => $salesInvoice->notes,
                'subtotal' => $this->decimal($salesInvoice->subtotal),
                'discount' => $this->decimal($salesInvoice->discount),
                'has_discount' => bccomp((string) $salesInvoice->discount, '0', 2) !== 0,
                'grand_total' => $this->decimal($salesInvoice->grand_total),
                'distributor' => [
                    'name' => $salesInvoice->distributor?->name ?? '—',
                    'address' => $salesInvoice->distributor?->address,
                ],
                'lines' => $salesInvoice->lines->map(fn (SalesInvoiceLine $line): array => [
                    'product_name' => $line->product?->name_ar ?? '—',
                    'quantity' => QuantityDisplay::format($line->quantity),
                    'unit_price' => $this->decimal($line->unit_price),
                    'line_total' => $this->decimal($line->line_total),
                ])->values()->all(),
            ],
            'app_name' => $settings->app_name,
            'logo_url' => $settings->displayLogoUrl(),
            'invoice_header' => $settings->invoice_header,
            'invoice_footer' => $settings->invoice_footer,
            'back_url' => route('sales-invoices.create-edit', $salesInvoice),
        ]);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
