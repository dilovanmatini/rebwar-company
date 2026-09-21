<?php

namespace App\Actions\Report;

use App\Actions\Report\Concerns\PreparesReportPrintView;
use App\Services\InventoryReportBuilder;
use App\Services\ReportBuilder;
use App\Services\SalesByProductReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrintAction
{
    use PreparesReportPrintView;

    public function __construct(
        public ReportBuilder $builder,
        public InventoryReportBuilder $inventoryBuilder,
        public SalesByProductReportBuilder $salesBuilder,
    ) {}

    public function handle(Request $request, string $report): Response
    {
        return response()->view($this->printViewName($report), $this->printViewData($request, $report));
    }
}
