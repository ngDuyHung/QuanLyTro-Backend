<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\ExportLeasePdfRequest;
use App\Http\Requests\Setting\ExportLedgerPdfRequest;
use App\Http\Requests\Setting\SaveContractTemplateRequest;
use App\Http\Requests\Setting\SaveLedgerTemplateRequest;
use App\Http\Resources\Setting\SettingResource;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    /**
     * API: Lấy mẫu hợp đồng
     */
    public function getContractTemplate(Request $request): JsonResponse
    {
        $template = $this->settingService->getContractTemplate($request->user()->id);

        // Trả về dữ liệu qua API Resource đúng chuẩn kiến trúc hệ thống
        return (new SettingResource($template))
            ->additional(['success' => true])
            ->response();
    }

    /**
     * API: Lưu mẫu hợp đồng
     */
    public function saveContractTemplate(SaveContractTemplateRequest $request): JsonResponse
    {
        $setting = $this->settingService->saveContractTemplate(
            $request->user()->id,
            $request->validated('template')
        );

        // Trả về dữ liệu mẫu mới lưu qua API Resource
        return (new SettingResource($setting))
            ->additional([
                'success' => true,
                'message' => 'Lưu mẫu hợp đồng thành công.'
            ])
            ->response();
    }

    /**
     * API: Xuất file PDF
     */
    public function exportLeasePdf(ExportLeasePdfRequest $request, int $id)
    {
        // Chắc chắn dữ liệu đã hợp lệ 100% mới chạy xuống đây
        $pdf = $this->settingService->generateLeasePdf($id, $request->user()->id);

        return $pdf->download("Hop_dong_thue_phong_{$id}.pdf");
    }



    /**
     * Lấy mẫu Hóa đơn
     */
    public function getInvoiceTemplate(Request $request)
    {
        $userId = $request->user()->id;
        $template = $this->settingService->getInvoiceTemplate($userId);

        return response()->json([
            'data' => [
                'template' => $template
            ]
        ]);
    }

    /**
     * Lưu mẫu Hóa đơn
     */
    public function saveInvoiceTemplate(Request $request)
    {
        $request->validate([
            'template' => 'nullable|string'
        ]);

        $userId = $request->user()->id;
        $this->settingService->saveInvoiceTemplate($userId, $request->input('template', ''));

        return response()->json([
            'message' => 'Đã lưu cấu hình mẫu hóa đơn thành công.'
        ]);
    }

    /**
     * Xuất PDF Hóa đơn
     */
    public function exportInvoicePdf(Request $request, $id)
    {
        $userId = $request->user()->id;

        // Gọi hàm từ Service bạn vừa thêm
        $pdf = $this->settingService->generateInvoicePdf((int) $id, $userId);

        // Trả về file PDF để trình duyệt có thể tải xuống
        return $pdf->download("Hoa_don_{$id}.pdf");
    }

    // /**
    //  * Lấy mẫu Sổ kế toán
    //  */
    // public function getLedgerTemplate(Request $request): JsonResponse
    // {
    //     $template = $this->settingService->getLedgerTemplate($request->user()->id);

    //     return (new SettingResource($template))
    //         ->additional(['success' => true])
    //         ->response();
    // }

    // /**
    //  * Lưu mẫu Sổ kế toán
    //  */
    // public function saveLedgerTemplate(SaveLedgerTemplateRequest $request): JsonResponse
    // {
    //     $setting = $this->settingService->saveLedgerTemplate(
    //         $request->user()->id,
    //         $request->validated('template')
    //     );

    //     return (new SettingResource($setting))
    //         ->additional([
    //             'success' => true,
    //             'message' => 'Lưu mẫu sổ kế toán thành công.'
    //         ])
    //         ->response();
    // }

    /**
     * Xuất PDF Sổ kế toán
     */
    public function exportLedgerPdf(ExportLedgerPdfRequest $request, int $id)
    {
        $pdf = $this->settingService->generateLedgerPdf($id, $request->user()->id);
        return $pdf->download("So_Ke_Toan_S1a_HKD_{$id}.pdf");
    }
}
