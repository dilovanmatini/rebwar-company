<?php

namespace App\Actions\Report;

use App\Actions\Report\Concerns\PreparesReportPrintView;
use App\Services\InventoryReportBuilder;
use App\Services\ReportBuilder;
use App\Services\SalesByProductReportBuilder;
use App\Support\ArabicPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PdfAction
{
    use PreparesReportPrintView;

    public function __construct(
        public ReportBuilder $builder,
        public InventoryReportBuilder $inventoryBuilder,
        public SalesByProductReportBuilder $salesBuilder,
    ) {}

    public function handle(Request $request, string $report): Response
    {
        $viewData = $this->printViewData($request, $report);

        return ArabicPdf::fromView(
            $this->printViewName($report),
            $viewData,
            orientation: $this->printOrientation($report),
        )
            ->download(sprintf('report-%s-%s.pdf', $report, now()->format('Ymd')));
    }
}
