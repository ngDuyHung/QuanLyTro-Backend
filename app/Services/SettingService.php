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

    /**
     * Biên dịch HTML và tạo object PDF cho hợp đồng
     */
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
            '{{CREATED_DATE}}' => \Carbon\Carbon::parse($invoice->period_to)->format('d/m/Y'),
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

    public function compileLedgerHtml(int $ledgerId, int $userId): string
    {
        $ledger = \App\Models\AccountingLedger::with(['details', 'property'])
            ->where('user_id', $userId)
            ->findOrFail($ledgerId);

        // 1. Build cấu trúc HTML cho từng dòng chi tiết
        $rowsHtml = '';
        $detailCount = $ledger->details->count();

        foreach ($ledger->details as $detail) {
            $date = $detail->transaction_date ? \Carbon\Carbon::parse($detail->transaction_date)->format('d/m/Y') : '';
            $amount = number_format((float) $detail->amount, 0, ',', '.');

            $rowsHtml .= "<tr>
                <td align='center' style='padding: 8px;'>{$date}</td>
                <td style='padding: 8px;'>{$detail->description}</td>
                <td align='right' style='padding: 8px;'>{$amount}</td>
            </tr>";
        }

        // Bù dòng trống với khoảng trắng (&nbsp;) để DOMPDF không làm móp xẹp bảng
        if ($detailCount < 5) {
            $emptyRows = 5 - $detailCount;
            for ($i = 0; $i < $emptyRows; $i++) {
                $rowsHtml .= "<tr>
                    <td align='center' style='padding: 8px; height: 25px;'>&nbsp;</td>
                    <td style='padding: 8px;'>&nbsp;</td>
                    <td style='padding: 8px;'>&nbsp;</td>
                </tr>";
            }
        }

        // 2. Xác định kỳ hiển thị
        $periodLabel = match ($ledger->period_type) {
            'month' => "Tháng {$ledger->period_month} năm {$ledger->period_year}",
            'quarter' => "Quý {$ledger->period_quarter} năm {$ledger->period_year}",
            'year' => "Năm {$ledger->period_year}",
            default => "Năm {$ledger->period_year}",
        };

        // 3. Chuẩn bị các biến dữ liệu
        $representative_name = $ledger->property ? mb_strtoupper($ledger->property->representative_name, 'UTF-8') : '................................';
        $propertyAddress = $ledger->property ? $ledger->property->address : '................................................';
        $taxCode = ($ledger->property && $ledger->property->tax_code) ? $ledger->property->tax_code : '................................';
        $repName = ($ledger->property && $ledger->property->representative_name) ? mb_strtoupper($ledger->property->representative_name, 'UTF-8') : '';

        $totalRevenue = number_format((float) $ledger->total_revenue, 0, ',', '.');
        $currentDay = date('d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        // 4. MẪU HTML CỐ ĐỊNH CHUẨN S1a-HKD (Khớp 100% mẫu Bộ Tài Chính)
        $html = <<<HTML
        <div style="font-family: 'DejaVu Sans', sans-serif; font-size: 13px; line-height: 1.5; color: #000; padding: 10px;">
            
            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 15px;">
                <tr>
                    <td width="55%" valign="top">
                        <strong style="font-size: 13px; text-transform: uppercase;">HỘ, CÁ NHÂN KINH DOANH: {$representative_name}</strong><br>
                        Địa chỉ: {$propertyAddress}<br>
                        Mã số thuế: {$taxCode}
                    </td>
                    <td width="45%" align="center" valign="top">
                        <strong style="font-size: 13px;">Mẫu số S1a-HKD</strong><br>
                        <i style="font-size: 11px;">(Kèm theo Thông tư số 152/2025/TT-BTC<br> ngày 31 tháng 12 năm 2025 của Bộ trưởng<br>Bộ Tài chính)</i>
                    </td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 15px; margin-bottom: 15px;">
                <h2 style="font-weight: bold; margin: 0 0 5px 0; font-size: 16px;">SỔ DOANH THU BÁN HÀNG HÓA, DỊCH VỤ</h2>
                <p style="margin: 0 0 5px 0;">Địa điểm kinh doanh: {$propertyAddress}</p>
                <p style="margin: 0 0 5px 0;">Kỳ kê khai: {$periodLabel}</p>
            </div>

            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 5px;">
                <tr>
                    <td align="right"><span style="font-size: 13px;">Đơn vị tính: VNĐ</span></td>
                </tr>
            </table>

            <table width="100%" border="1" cellspacing="0" cellpadding="0" style="border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
                <thead>
                    <tr style="font-weight: bold; text-align: center;">
                        <th width="20%" style="padding: 8px;">Ngày tháng</th>
                        <th width="55%" style="padding: 8px;">Diễn giải</th>
                        <th width="25%" style="padding: 8px;">Số tiền</th>
                    </tr>
                    <tr style="text-align: center; font-weight: bold;">
                        <td style="padding: 4px;">A</td>
                        <td style="padding: 4px;">B</td>
                        <td style="padding: 4px;">1</td>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold;">
                        <td colspan="2" align="center" style="padding: 8px;">Tổng cộng</td>
                        <td align="right" style="padding: 8px;">{$totalRevenue}</td>
                    </tr>
                </tfoot>
            </table>

            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-top: 30px;">
                <tr>
                    <td width="50%" align="center"></td>
                    <td width="50%" align="center">
                        <i style="font-size: 13px;">Ngày {$currentDay} tháng {$currentMonth} năm {$currentYear}</i><br>
                        <strong style="font-size: 13px;">NGƯỜI ĐẠI DIỆN HỘ KINH DOANH/<br>CÁ NHÂN KINH DOANH</strong><br>
                        <i style="font-size: 12px;">(Ký, ghi rõ họ tên, đóng dấu (nếu có))</i><br><br><br><br><br><br>
                        <strong>{$repName}</strong>
                    </td>
                </tr>
            </table>
        </div>
        HTML;

        return $html;
    }

    /**
     * Biên dịch HTML và sinh PDF Sổ kế toán Mẫu S1a-HKD
     */
    public function generateLedgerPdf(int $ledgerId, int $userId)
    {
        $compiledHtml = $this->compileLedgerHtml($ledgerId, $userId);

        // Bọc toàn bộ nội dung vào khung HTML chuẩn để ép font tiếng Việt (DejaVu Sans)
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

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($fullHtml)->setPaper('A4', 'portrait');
    }




    /**
     * Dùng Imagick để chuyển trang 1 của file PDF (DomPDF) thành ảnh PNG
     */
    // public function generateInvoicePdfImage(int $invoiceId, int $userId)
    // {
    //     try {
    //         // 1. Khởi tạo và lấy file PDF từ hàm đã có sẵn
    //         $pdf = $this->generateInvoicePdf($invoiceId, $userId);
    //         $pdfData = $pdf->output(); // Lấy raw binary của PDF

    //         // 2. Khởi tạo Imagick để xử lý ảnh
    //         $im = new \Imagick();

    //         // BẮT BUỘC: Set độ phân giải (DPI) TRƯỚC khi đọc ảnh để ảnh sau khi xuất ra được nét (như HD)
    //         $im->setResolution(300, 300);

    //         // Đọc nội dung PDF từ chuỗi binary
    //         $im->readImageBlob($pdfData);

    //         // Chọn trang đầu tiên của hóa đơn (Index 0)
    //         $im->setIteratorIndex(0);

    //         // Cài đặt định dạng xuất ra là PNG
    //         $im->setImageFormat('png');

    //         // XỬ LÝ NỀN TRẮNG: Rất quan trọng vì PDF mặc định không có màu nền. 
    //         // Nếu không có đoạn này, ảnh xuất ra có thể bị nền đen hoặc trong suốt.
    //         $im->setImageBackgroundColor('white');
    //         $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
    //         $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

    //         // Lấy dữ liệu ảnh
    //         $imageBlob = $im->getImageBlob();

    //         // Giải phóng bộ nhớ RAM ngay lập tức
    //         $im->clear();
    //         $im->destroy();

    //         // Encode sang Base64 để tương thích 100% với InvoiceController hiện tại của bạn
    //         return base64_encode($imageBlob);
    //     } catch (\Exception $e) {
    //         \Illuminate\Support\Facades\Log::error('Lỗi xuất ảnh bằng Imagick: ' . $e->getMessage());
    //         throw new \App\Exceptions\Domain\BusinessException(
    //             'Lỗi hệ thống: Không thể xuất ảnh hóa đơn. Máy chủ có thể không hỗ trợ Imagick/Ghostscript.'
    //         );
    //     }
    // }
}
