<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportMasterDataRequest;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
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

            return response()->json([
                'message' => "Import hoàn tất. Thành công: {$report['success_count']} dòng, Thất bại: {$report['failed_count']} dòng.",
                'data' => $report
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
    public function downloadTemplate()
    {
        try {
            return $this->importService->downloadTemplate();
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Không thể khởi tạo file mẫu lúc này.',
                'error'   => $exception->getMessage()
            ], 500);
        }
    }
}
