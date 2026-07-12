<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportMasterDataRequest;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ImportController extends Controller
{
    // Inject ImportService vào Controller
    public function __construct(
        private readonly ImportService $importService
    ) {}

    public function importMasterData(ImportMasterDataRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $file = $request->file('file');

            // Giao toàn bộ việc xử lý file cho Service layer
            $report = $this->importService->processMasterData($file, $userId);

            // Nếu không có dòng nào thành công mà có dòng thất bại
            if ($report['success_count'] === 0 && $report['failed_count'] > 0) {
                return response()->json([
                    'message' => 'Import dữ liệu thất bại. Vui lòng kiểm tra lại danh sách lỗi.',
                    'data' => $report
                ], 422);
            }

            // NẾU CÓ BẤT KỲ DÒNG NÀO THẤT BẠI (Dù các dòng khác thành công), 
            // Trả về mã 422 để Frontend bắt buộc phải bung bảng danh sách lỗi ra cho chủ nhà xem.
            if ($report['failed_count'] > 0) {
                return response()->json([
                    'message' => "Import hoàn tất một phần. Thành công: {$report['success_count']} dòng. Thất bại: {$report['failed_count']} dòng. Vui lòng xem chi tiết lỗi!",
                    'data'    => $report
                ], 422); // Mã 422 Unprocessable Entity
            }

            // Nếu thành công 100% không có lỗi nào
            return response()->json([
                'message' => "Tuyệt vời! Đã import thành công toàn bộ {$report['success_count']} dòng dữ liệu.",
                'data'    => $report
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi nghiêm trọng trong quá trình xử lý file.',
                'error'   => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Tải file Excel cấu trúc mẫu kèm dữ liệu ví dụ
     */
    public function downloadTemplate(Request $request)
    {
        try {
            $userId = $request->user()->id; // Lấy ID người dùng
            return $this->importService->downloadTemplate($userId);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Không thể khởi tạo file mẫu lúc này.',
                'error'   => $exception->getMessage()
            ], 500);
        }
    }
}
