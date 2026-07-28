<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;
use App\Exports\LedgerReportExport;
use Maatwebsite\Excel\Facades\Excel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\GetReportRequest;
use App\Models\Property;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * 1. Báo cáo Tài chính & Dòng tiền
     * GET /api/v1/reports/financial
     */
    public function getFinancialReport(GetReportRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $propertyId = $request->filled('property_id') ? (int) $request->property_id : null;
        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        $data = $this->reportService->getFinancialReport($userId, $propertyId, $fromDate, $toDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 2. Báo cáo Sổ quỹ
     * GET /api/v1/reports/ledger
     */
    public function getLedgerReport(GetReportRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $propertyId = $request->filled('property_id') ? (int) $request->property_id : null;
        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        $data = $this->reportService->getLedgerReport($userId, $propertyId, $fromDate, $toDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 3. Báo cáo Công nợ
     * GET /api/v1/reports/debt
     */
    public function getDebtReport(GetReportRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $propertyId = $request->filled('property_id') ? (int) $request->property_id : null;

        // Báo cáo công nợ tính tại thời điểm hiện tại (Real-time snapshot), 
        // nên không cần truyền from_date và to_date vào Service.
        $data = $this->reportService->getDebtReport($userId, $propertyId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 4. Báo cáo Vận hành (Khai thác & Sự cố)
     * GET /api/v1/reports/occupancy
     */
    public function getOccupancyReport(GetReportRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $propertyId = $request->filled('property_id') ? (int) $request->property_id : null;
        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        $data = $this->reportService->getOccupancyReport($userId, $propertyId, $fromDate, $toDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 5. Xuất Excel Báo cáo Sổ quỹ
     * GET /api/v1/reports/ledger/export/excel
     */
    public function exportLedgerExcel(GetReportRequest $request)
    {
        $userId = $request->user()->id;
        $propertyId = $request->filled('property_id') ? (int) $request->property_id : null;
        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        // Lấy tên khu nhà để in ra tiêu đề Excel
        $propertyName = 'Tất cả khu nhà';
        if ($propertyId) {
            $property = Property::where('user_id', $userId)->find($propertyId);
            if ($property) {
                $propertyName = $property->name;
            }
        }

        // Tận dụng lại hàm lấy data của Service
        $ledgerData = $this->reportService->getLedgerReport($userId, $propertyId, $fromDate, $toDate);

        // Tên file tải về
        $fileName = 'So_Quy_' . Carbon::parse($fromDate)->format('dmY') . '-' . Carbon::parse($toDate)->format('dmY') . '.xlsx';

        // Gọi package Maatwebsite\Excel để xuất file trực tiếp
        return Excel::download(
            new LedgerReportExport($ledgerData, $fromDate, $toDate, $propertyName),
            $fileName
        );
    }
}
