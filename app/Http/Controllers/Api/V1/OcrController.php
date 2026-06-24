<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ocr\ScanIdCardRequest;
use App\Http\Resources\Ocr\OcrResource;
use App\Services\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OcrController extends Controller
{
    public function __construct(
        private readonly OcrService $ocrService
    ) {}

    /**
     * Nhận file ảnh từ Client và trả về thông tin trích xuất
     */
    public function scanIdCard(ScanIdCardRequest $request): JsonResponse
    {
        try {
            // Gọi Service xử lý nghiệp vụ AI
            $data = $this->ocrService->extractIdCardData($request->file('image'));

            // Trả về dữ liệu qua API Resource đúng chuẩn của hệ thống
            return (new OcrResource($data))
                ->additional(['message' => 'Trích xuất dữ liệu CCCD thành công.'])
                ->response();
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Không thể đọc được thông tin CCCD. Vui lòng nhập thủ công hoặc chụp lại ảnh rõ nét hơn.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
