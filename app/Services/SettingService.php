<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lease;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class SettingService
{
    /**
     * Lấy mẫu HTML của hợp đồng
     */
    public function getContractTemplate(int $userId): string
    {
        $setting = Setting::where('key', 'contract_template')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->orderBy('user_id', 'desc')
            ->first();

        return $setting ? $setting->value : '';
    }

    /**
     * Lưu mẫu HTML do chủ trọ chỉnh sửa
     */
    public function saveContractTemplate(int $userId, string $template): Setting
    {
        return Setting::updateOrCreate(
            ['user_id' => $userId, 'key' => 'contract_template'],
            ['value' => $template]
        );
    }

    /**
     * Biên dịch HTML và tạo object PDF
     */
    public function generateLeasePdf(int $leaseId, int $userId)
    {
        // 1. Lấy dữ liệu hợp đồng thực tế
        $lease = Lease::with(['tenant', 'room.property.user'])
            ->whereHas('room.property', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->find($leaseId);

        // 2. Lấy mẫu hợp đồng
        $html = $this->getContractTemplate($userId);

        if (empty($html)) {
            $html = '<h1>Chưa có mẫu hợp đồng</h1>';
        }

        // 3. Chuẩn bị bộ từ điển biến động (Shortcodes)
        $replacePairs = [
            '{{CURRENT_DAY}}' => date('d'),
            '{{CURRENT_MONTH}}' => date('m'),
            '{{CURRENT_YEAR}}' => date('Y'),

            '{{LANDLORD_NAME}}' => $lease->room->property->user->name ?? '',
            '{{LANDLORD_PHONE}}' => $lease->room->property->user->phone ?? '',

            '{{TENANT_NAME}}' => $lease->tenant->full_name ?? '',
            '{{TENANT_PHONE}}' => $lease->tenant->phone ?? '',
            '{{TENANT_CCCD}}' => $lease->tenant->id_card_number ?? '',

            '{{ROOM_NAME}}' => $lease->room->name ?? '',
            '{{ROOM_PRICE}}' => number_format((float) $lease->room->current_price, 0, ',', '.'),
            '{{DEPOSIT}}' => number_format((float) $lease->deposit, 0, ',', '.'),
            '{{START_DATE}}' => Carbon::parse($lease->start_date)->format('d/m/Y'),

            '{{PROPERTY_NAME}}' => $lease->room->property->name ?? '',
            '{{PROPERTY_ADDRESS}}' => $lease->room->property->address ?? '',
        ];

        // 4. Thay thế biến thành dữ liệu thật
        $compiledHtml = str_replace(array_keys($replacePairs), array_values($replacePairs), $html);

        // 4.5. Bọc toàn bộ nội dung vào khung HTML chuẩn để ép font tiếng Việt (DejaVu Sans)
        $fullHtml = <<<HTML
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                body { 
                    font-family: 'DejaVu Sans', sans-serif; 
                    font-size: 14px;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            {$compiledHtml}
        </body>
        </html>
        HTML;
        // 5. Khởi tạo PDF
        return Pdf::loadHTML($fullHtml)->setPaper('A4', 'portrait')->setWarnings(false);
    }
}
