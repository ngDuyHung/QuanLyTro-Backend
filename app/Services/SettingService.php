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

    // hàm biên dịch HTML hợp đồng với dữ liệu thực tế của hợp đồng
    public function compileLeaseHtml(int $leaseId, int $userId): string
    {
        $lease = \App\Models\Lease::with(['tenant', 'room.property.user', 'serviceItems'])
            ->whereHas('room.property', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->findOrFail($leaseId);

        $html = $this->getContractTemplate($userId);
        if (empty($html)) {
            $html = '<div style="text-align:center; padding: 50px; font-family: sans-serif;">
                        <h2>Chưa có mẫu hợp đồng</h2>
                        <p>Vui lòng cấu hình mẫu hợp đồng trong hệ thống.</p>
                     </div>';
        }

        // Lấy danh sách dịch vụ kèm theo hợp đồng và hiển thị ra HTML
        $applicablePrices = \App\Models\ServicePrice::getApplicablePrices((int)$lease->room->property_id);
        $servicesHtml = '<ul style="margin-top: 5px; margin-bottom: 15px; list-style-type: disc; padding-left: 20px;">';
        $activeServices = $lease->serviceItems->whereNull('expiry_date');

        if ($activeServices->isEmpty()) {
            $servicesHtml .= '<li>Không có dịch vụ đăng ký kèm theo.</li>';
        } else {
            foreach ($activeServices as $item) {
                $typeValue = is_object($item->service_type) ? $item->service_type->value : $item->service_type;

                $label = 'Dịch vụ';
                if (is_object($item->service_type) && method_exists($item->service_type, 'label')) {
                    $label = $item->service_type->label();
                } elseif (class_exists(\App\Enums\ServiceType::class)) {
                    $enum = \App\Enums\ServiceType::tryFrom($typeValue);
                    if ($enum) $label = $enum->label();
                }

                $priceRule = $applicablePrices->first(function ($p) use ($typeValue) {
                    $pt = is_object($p->service_type) ? $p->service_type->value : $p->service_type;
                    return $pt === $typeValue;
                });

                $unitPrice = $item->custom_price ?? ($priceRule ? $priceRule->unit_price : 0);
                $formattedPrice = number_format((float)$unitPrice, 0, ',', '.');
                $unit = match ($typeValue) {
                    'electricity' => 'kWh',
                    'water' => 'khối',
                    default => 'tháng'
                };
                $servicesHtml .= "<li style='margin-bottom: 3px;'><strong>{$label}:</strong> {$formattedPrice} VNĐ/{$unit}</li>";
            }
        }
        $servicesHtml .= '</ul>';

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
            '{{START_DATE}}' => \Carbon\Carbon::parse($lease->start_date)->format('d/m/Y'),
            '{{PROPERTY_NAME}}' => $lease->room->property->name ?? '',
            '{{PROPERTY_ADDRESS}}' => $lease->room->property->address ?? '',
            '{{SERVICES_LIST}}' => $servicesHtml,
        ];

        return str_replace(array_keys($replacePairs), array_values($replacePairs), $html);
    }

    // Sửa lại hàm generateLeasePdf hiện tại để tái sử dụng mã
    public function generateLeasePdf(int $leaseId, int $userId)
    {
        $compiledHtml = $this->compileLeaseHtml($leaseId, $userId);

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

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($fullHtml)->setPaper('A4', 'portrait')->setWarnings(false);
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

        // Gọi hàm dùng chung lấy HTML rột
        $compiledHtml = $this->compileInvoiceHtml($invoiceId, $userId);

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


    public function compileInvoiceHtml(int $invoiceId, int $userId): string
    {
        // 1. Lấy dữ liệu hóa đơn thực tế kèm theo các relation cần thiết
        // Cần lấy items để render bảng, lease.tenant để lấy tên khách, room.property.user để lấy tên chủ
        $invoice = \App\Models\Invoice::with(['items', 'meterReadings', 'lease.tenant', 'room.property.user'])
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

        // 3. Render bảng HTML chi tiết các khoản thu (Đã thêm chú thích)
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
            $free = (float) $item->free_quantity_snapshot;
            $price = number_format((float) $item->unit_price_snapshot, 0, ',', '.');
            $amount = number_format((float) $item->amount, 0, ',', '.');

            // -- LOGIC GHI CHÚ CHỈ SỐ --
            $detailText = '';
            if (in_array($item->charge_type, ['electricity', 'water'])) {
                $meter = $invoice->meterReadings->where('type', $item->charge_type)->first();
                if ($meter) {
                    $detailText = "<br><span style='font-size: 11px; color: #64748b; font-weight: normal;'>(Số cũ: {$meter->previous_reading} - Số mới: {$meter->current_reading}";
                    if ($free > 0) $detailText .= " - Miễn phí: {$free}";
                    $detailText .= ")</span>";
                } elseif ($free > 0) {
                    $detailText = "<br><span style='font-size: 11px; color: #64748b; font-weight: normal;'>(Được miễn phí: {$free} {$item->unit})</span>";
                }
            }
            // BỔ SUNG ĐOẠN NÀY ĐỂ CHÚ THÍCH TIỀN THẾ CHÂN
            elseif ($item->charge_type === 'deposit') {
                $detailText = "<br><span style='font-size: 11px; color: #d97706; font-style: italic;'>* Khoản này sẽ được hoàn trả khi trả phòng nếu không phát sinh nợ/hư hỏng.</span>";
            }
            // GIỮ NGUYÊN PHẦN CÒN LẠI
            elseif ($free > 0) {
                $detailText = "<br><span style='font-size: 11px; color: #64748b; font-weight: normal;'>(Được miễn phí: {$free} {$item->unit})</span>";
            }

            // Gắn $detailText ngay dưới Tên dịch vụ
            $itemsHtml .= "<tr>
                <td style='border: 1px solid #e2e8f0; padding: 10px;'>
                    <strong>{$item->description}</strong>{$detailText}
                </td>
                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: center;'>{$qty} {$item->unit}</td>
                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: right;'>{$price}</td>
                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: right; font-weight: bold;'>{$amount}</td>
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

        // Trả về HTML đã thay thế dữ liệu thật
        return str_replace(array_keys($replacePairs), array_values($replacePairs), $html);
    }
}
