<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    /**
     * Gửi ảnh CCCD sang Gemini để trích xuất thông tin
     */
    public function extractIdCardData(UploadedFile $image): array
    {
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            throw new Exception('Server chưa cấu hình GEMINI_API_KEY.');
        }

        // 1. Chuẩn bị ảnh (Chuyển sang Base64)
        $imageData = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        // 2. Prompt chỉ định rõ lấy Tên và CCCD
        $prompt = <<<PROMPT
Bạn là hệ thống trích xuất dữ liệu (OCR). Hãy đọc ảnh thẻ Căn cước công dân (CCCD) này.
Hãy tìm và trích xuất đúng 2 thông tin: Họ tên và Số định danh/CCCD.
Chỉ trả về JSON hợp lệ, không kèm markdown, theo cấu trúc:
{
  "full_name": "TÊN CỦA NGƯỜI ĐÓ",
  "id_card_number": "SỐ CCCD"
}
PROMPT;

        // 3. Gọi API (Sử dụng model gemini-3.1-flash-lite )
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $apiKey;

        $response = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $imageData
                            ]
                        ]
                    ]
                ]
            ],
            // Ép Gemini trả về chuẩn JSON
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            Log::error('Gemini API Error: ' . $response->body());
            throw new Exception('Lỗi khi kết nối với dịch vụ AI.');
        }

        $result = $response->json();
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (trim($text) === '') {
            throw new Exception('Gemini không trả về nội dung.');
        }

        // 4. Gọi hàm dọn dẹp và parse JSON
        return $this->parseJsonResponse($text);
    }



    /**
     * Kỹ thuật dọn dẹp JSON từ mẫu của bạn
     */
    private function parseJsonResponse(string $text): array
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^```json\s*/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^```\s*/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/u', '', $cleaned) ?? $cleaned;

        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new Exception('Không tìm thấy cấu trúc JSON hợp lệ từ kết quả.');
        }

        $json = substr($cleaned, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new Exception('JSON trả về không thể giải mã.');
        }

        // Đảm bảo luôn trả về đúng các key mong muốn
        return [
            'full_name' => $decoded['full_name'] ?? '',
            'id_card_number' => $decoded['id_card_number'] ?? '',
        ];
    }


    /**
     * Gửi ảnh Đồng hồ điện/nước sang Gemini để trích xuất chỉ số
     */
    public function extractMeterReading(UploadedFile $image, string $type): array
    {
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            throw new Exception('Server chưa cấu hình GEMINI_API_KEY.');
        }

        $imageData = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        $meterName = $type === 'electricity' ? 'đồng hồ điện (công tơ điện)' : 'đồng hồ nước';

        // Prompt hướng dẫn AI bỏ qua phần thập phân màu đỏ và kim quay
        $prompt = <<<PROMPT
Bạn là hệ thống trích xuất dữ liệu (OCR). Hãy đọc ảnh chụp {$meterName} này.
Nhiệm vụ: Trích xuất chỉ số tiêu thụ chính đang hiển thị.
Quy tắc:
1. Chỉ lấy phần số nguyên trên dãy số chính hoặc màn hình điện tử.
2. Tuyệt đối BỎ QUA các kim đồng hồ xoay nhỏ bên dưới (nếu có).
3. BỎ QUA các chữ số thập phân (thường có màu đỏ hoặc ở vòng quay riêng bên phải).
Chỉ trả về JSON hợp lệ, không kèm markdown, theo cấu trúc:
{
  "reading": 1234
}
PROMPT;

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $apiKey;

        $response = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $imageData
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            throw new Exception('Lỗi khi kết nối với dịch vụ AI.');
        }

        $result = $response->json();
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (trim($text) === '') {
            throw new Exception('Gemini không trả về nội dung.');
        }
        
        // Gọi hàm dọn dẹp JSON dành riêng cho đồng hồ
        return $this->parseMeterJsonResponse($text);
    }


    /**
     * Dọn dẹp và parse JSON dành riêng cho Đồng hồ điện nước
     */
    private function parseMeterJsonResponse(string $text): array
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^```json\s*/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^```\s*/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/u', '', $cleaned) ?? $cleaned;

        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new Exception('Không tìm thấy cấu trúc JSON hợp lệ từ kết quả.');
        }

        $json = substr($cleaned, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new Exception('JSON trả về không thể giải mã.');
        }

        return [
            'reading' => isset($decoded['reading']) ? (int) $decoded['reading'] : null,
        ];
    }
}
