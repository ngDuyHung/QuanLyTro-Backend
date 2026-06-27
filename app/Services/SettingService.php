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


    /**
     * Lấy mẫu HTML của hóa đơn
     */
    public function getInvoiceTemplate(int $userId): string
    {
        $setting = Setting::where('key', 'invoice_template')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->orderBy('user_id', 'desc')
            ->first();

        return $setting ? $setting->value : '';
    }

    /**
     * Lưu mẫu HTML do chủ trọ chỉnh sửa cho hóa đơn
     */
    public function saveInvoiceTemplate(int $userId, string $template): Setting
    {
        return Setting::updateOrCreate(
            ['user_id' => $userId, 'key' => 'invoice_template'],
            ['value' => $template]
        );
    }

    /**
     * Biên dịch HTML và tạo object PDF cho Hóa đơn
     */
    public function generateInvoicePdf(int $invoiceId, int $userId)
    {
        // 1. Lấy dữ liệu hóa đơn thực tế kèm theo các relation cần thiết
        // Cần lấy items để render bảng, lease.tenant để lấy tên khách, room.property.user để lấy tên chủ
        $invoice = \App\Models\Invoice::with(['items', 'lease.tenant', 'room.property.user'])
            ->whereHas('room.property', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->findOrFail($invoiceId);

        // 2. Lấy mẫu hóa đơn
        $html = $this->getInvoiceTemplate($userId);

        if (empty($html)) {
            $html = '<div style="text-align:center; padding: 50px; font-family: sans-serif;">
                        <h2>Chưa có mẫu hóa đơn</h2>
                        <p>Vui lòng cấu hình mẫu phiếu thu trong hệ thống.</p>
                     </div>';
        }

        // 3. Render bảng HTML chi tiết các khoản thu (Items)
        // Đây là điểm khác biệt cốt lõi so với hợp đồng
        $itemsHtml = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px;">
                        <thead>
                            <tr style="background-color: #f8fafc;">
                                <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: left; font-weight: bold;">Nội dung thu</th>
                                <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: center; font-weight: bold;">Số lượng</th>
                                <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: right; font-weight: bold;">Đơn giá</th>
                                <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: right; font-weight: bold;">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>';

        foreach ($invoice->items as $item) {
            $qty = (float) $item->quantity;
            $price = number_format((float) $item->unit_price_snapshot, 0, ',', '.');
            $amount = number_format((float) $item->amount, 0, ',', '.');

            $itemsHtml .= "<tr>
                <td style='border: 1px solid #e2e8f0; padding: 10px;'>{$item->description}</td>
                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: center;'>{$qty} {$item->unit}</td>
                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: right;'>{$price}</td>
                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: right;'>{$amount}</td>
            </tr>";
        }
        $itemsHtml .= '</tbody></table>';

        // [Thêm đoạn này vào ngay trước mảng $replacePairs]
        $statusLabel = match ((string) $invoice->status) {
            'draft' => 'Bản nháp',
            'issued' => 'Chờ thanh toán',
            'partially_paid' => 'Thanh toán một phần',
            'paid' => 'ĐÃ THANH TOÁN ĐỦ',
            'overdue' => 'Quá hạn thanh toán',
            'cancelled' => 'Hóa đơn đã hủy',
            default => (string) $invoice->status,
        };

        // 4. Chuẩn bị bộ từ điển biến động (Shortcodes)
        $replacePairs = [
            '{{INVOICE_CODE}}' => $invoice->invoice_code ?? '',
            '{{STATUS}}' => $statusLabel,
            '{{MONTH_YEAR}}' => $invoice->period_to ? \Carbon\Carbon::parse($invoice->period_to)->format('m/Y') : '',
            '{{CREATED_DATE}}' => \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y'),
            '{{DUE_DATE}}' => $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : 'Không có',

            '{{LANDLORD_NAME}}' => $invoice->room->property->user->name ?? '',
            '{{LANDLORD_PHONE}}' => $invoice->room->property->user->phone ?? '',

            '{{TENANT_NAME}}' => $invoice->lease->tenant->full_name ?? '',
            '{{TENANT_PHONE}}' => $invoice->lease->tenant->phone ?? '',

            '{{ROOM_NAME}}' => $invoice->room->name ?? '',
            '{{PROPERTY_NAME}}' => $invoice->room->property->name ?? '',
            '{{PROPERTY_ADDRESS}}' => $invoice->room->property->address ?? '',

            '{{SUBTOTAL}}' => number_format((float) $invoice->subtotal_amount, 0, ',', '.'),
            '{{DISCOUNT}}' => number_format((float) $invoice->discount_amount, 0, ',', '.'),
            '{{TOTAL_AMOUNT}}' => number_format((float) $invoice->total_amount, 0, ',', '.'),
            '{{PAID_AMOUNT}}' => number_format((float) $invoice->paid_amount, 0, ',', '.'),
            '{{REMAINING_AMOUNT}}' => number_format((float) $invoice->remaining_amount, 0, ',', '.'),

            // Chèn nguyên cái bảng HTML vừa tạo vào đây
            '{{INVOICE_ITEMS_TABLE}}' => $itemsHtml,
        ];

        // 5. Thay thế biến thành dữ liệu thật
        $compiledHtml = str_replace(array_keys($replacePairs), array_values($replacePairs), $html);

        // 6. Bọc toàn bộ nội dung vào khung HTML chuẩn để ép font tiếng Việt (DejaVu Sans)
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
                    color: #1e293b;
                }
            </style>
        </head>
        <body>
            {$compiledHtml}
        </body>
        </html>
        HTML;

        // 7. Khởi tạo PDF
        return Pdf::loadHTML($fullHtml)->setPaper('A5', 'portrait')->setWarnings(false);
    }
}
