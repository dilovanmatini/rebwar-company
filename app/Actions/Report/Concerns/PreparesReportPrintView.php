<?php

namespace App\Actions\Report\Concerns;

use App\Enums\ReportType;
use App\Models\SystemSetting;
use App\Services\InventoryReportBuilder;
use App\Services\ReportBuilder;
use App\Services\SalesByProductReportBuilder;
use Illuminate\Http\Request;

/**
 * @property ReportBuilder $builder
 * @property InventoryReportBuilder $inventoryBuilder
 * @property SalesByProductReportBuilder $salesBuilder
 */
trait PreparesReportPrintView
{
    use ValidatesReportDates;

    /**
     * @return array{report: array<string, mixed>, generated_at: string, print_date: string, app_name: string, forPdf: bool}
     */
    protected function printViewData(Request $request, string $report): array
    {
        $type = ReportType::from($report);
        [$fromDate, $toDate] = $this->validatedDates($request, $type);
        $generatedAt = now()->timezone(config('app.timezone'));

        $payload = match ($type) {
            ReportType::Inventory => $this->inventoryBuilder->build(),
            ReportType::Sales => $this->salesBuilder->build($fromDate, $toDate),
            default => $this->builder->build($type, $fromDate, $toDate),
        };

        return [
            'report' => $payload,
            'generated_at' => $generatedAt->format('Y-m-d H:i'),
            'print_date' => $generatedAt->format('Y-m-d'),
            'app_name' => SystemSetting::current()->app_name,
            'forPdf' => false,
        ];
    }

    protected function printViewName(string $report): string
    {
        return match (ReportType::from($report)) {
            ReportType::Inventory => 'reports.inventory-print',
            ReportType::Sales => 'reports.sales-print',
            default => 'reports.print',
        };
    }

    protected function printOrientation(string $report): string
    {
        return ReportType::from($report) === ReportType::Sales
            ? 'portrait'
            : 'landscape';
    }
}
