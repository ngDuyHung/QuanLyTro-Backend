<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SepayConfig\SaveSepayConfigRequest;
use App\Models\SepayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SepayConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $config = SepayConfig::forUser($request->user()->id);
        return response()->json(['data' => $config->toArray()]);
    }

    public function save(SaveSepayConfigRequest $request): JsonResponse
    {
        $config = SepayConfig::forUser($request->user()->id);
        
        $config->save($request->mappedData());

        return response()->json([
            'message' => 'Cập nhật cấu hình SePay thành công.',
            'data' => $config->toArray(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $config = SepayConfig::forUser($request->user()->id);
        $config->clear();

        return response()->json([
            'message' => 'Đã ngắt kết nối và xóa toàn bộ cấu hình SePay.',
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $config = SepayConfig::forUser($request->user()->id);
        $token = $config->apiToken();

        if (!$token) {
            return response()->json(['message' => 'Chưa cấu hình API Token.'], 400);
        }

        // Test gọi thử API của SePay để xác thực token
        $response = Http::withToken($token)->get('https://my.sepay.vn/userapi/transactions/list', ['limit' => 1]);

        if ($response->successful()) {
            return response()->json(['message' => 'Kết nối API SePay thành công!']);
        }

        return response()->json(['message' => 'Kết nối thất bại. Vui lòng kiểm tra lại API Token.'], 402);
    }
}