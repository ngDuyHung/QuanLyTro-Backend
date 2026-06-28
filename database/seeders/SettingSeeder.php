<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // Mẫu hợp đồng chuẩn, có chứa các biến {{ }}
        $defaultContract = <<<HTML
        <div style="font-family: 'DejaVu Sans', sans-serif; font-size: 14px; line-height: 1.5;">
            <h2 style="text-align: center; font-weight: bold;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM<br>Độc lập - Tự do - Hạnh phúc</h2>
            <h3 style="text-align: center; margin-top: 20px;">HỢP ĐỒNG THUÊ PHÒNG TRỌ</h3>
            <p style="text-align: right;"><i>Hôm nay, ngày {{CURRENT_DAY}} tháng {{CURRENT_MONTH}} năm {{CURRENT_YEAR}}</i></p>
            <p><strong>BÊN CHO THUÊ (BÊN A):</strong> <span style="text-transform: uppercase;">{{LANDLORD_NAME}}</span></p>
            <p>- Số điện thoại: {{LANDLORD_PHONE}}</p>
            <p><strong>BÊN THUÊ (BÊN B):</strong> Ông/Bà <strong><span style="text-transform: uppercase;">{{TENANT_NAME}}</span></strong></p>
            <p>- Số điện thoại: {{TENANT_PHONE}}</p>
            <p>- Số CCCD/CMND: {{TENANT_CCCD}}</p>
            <p><strong>ĐIỀU 1: NỘI DUNG HỢP ĐỒNG</strong></p>
            <p>Bên A đồng ý cho Bên B thuê phòng số <strong>{{ROOM_NAME}}</strong> thuộc khu nhà <strong>{{PROPERTY_NAME}}</strong> tại địa chỉ {{PROPERTY_ADDRESS}}.</p>
            <p>- Giá thuê: <strong>{{ROOM_PRICE}} VNĐ/tháng</strong></p>
            <p>- Tiền cọc: <strong>{{DEPOSIT}} VNĐ</strong></p>
            <p>- Ngày bắt đầu tính tiền: {{START_DATE}}</p>
            <table style="width: 100%; margin-top: 50px; border: none;">
                <tr>
                    <td style="text-align: center; width: 50%;"><strong>ĐẠI DIỆN BÊN A</strong><br><br><br><br><br>{{LANDLORD_NAME}}</td>
                    <td style="text-align: center; width: 50%;"><strong>ĐẠI DIỆN BÊN B</strong><br><br><br><br><br>{{TENANT_NAME}}</td>
                </tr>
            </table>
        </div>
        HTML;

        $defaultInvoiceTemplate = <<<HTML
        <div style="max-width: 600px; margin: 0 auto; font-family: 'DejaVu Sans', sans-serif; color: #333333; font-size: 13px; line-height: 1.5;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-bottom: 2px solid #2c3e50; margin-bottom: 15px; padding-bottom: 10px;">
                <tr>
                <td width="60%" valign="top">
                    <p style="margin: 0 0 4px 0; font-size: 15px; font-weight: bold; color: #2c3e50;">{{PROPERTY_NAME}}</p>
                    <p style="margin: 0 0 2px 0; font-size: 12px;">Đ/c: {{PROPERTY_ADDRESS}}</p>
                    <p style="margin: 0; font-size: 12px;">ĐT: {{LANDLORD_PHONE}}</p>
                </td>
                <td width="40%" valign="top" align="right">
                    <p style="margin: 0 0 4px 0; font-size: 16px; font-weight: bold; color: #2c3e50;">PHIẾU THU</p>
                    <p style="margin: 0; font-size: 12px;">Số: {{INVOICE_CODE}}</p>
                    <p style="margin: 0; font-size: 12px; color: #27ae60; font-weight: bold;">{{STATUS}}</p>
                </td>
                </tr>
            </table>

            <div style="margin-bottom: 15px; background: #f4f4f4; padding: 10px; border-radius: 4px;">
                <p style="margin: 0 0 4px 0;">Khách hàng: <strong>{{TENANT_NAME}}</strong></p>
                <p style="margin: 0 0 4px 0;">Phòng: <strong>{{ROOM_NAME}}</strong></p>
                <p style="margin: 0;">Kỳ: {{MONTH_YEAR}} | Ngày lập: {{CREATED_DATE}}</p>
            </div>

            <p style="margin: 0 0 8px 0; font-weight: bold; color: #2c3e50;">I. CHI TIẾT CÁC KHOẢN PHÍ</p>
            {{INVOICE_ITEMS_TABLE}}

            <p style="margin: 15px 0 8px 0; font-weight: bold; color: #2c3e50;">II. TỔNG HỢP THANH TOÁN</p>
            <table width="100%" cellpadding="6" cellspacing="0" border="0" style="border-top: 1px solid #ccc;">
                <tr><td>Cộng tiền dịch vụ:</td><td align="right">{{SUBTOTAL}} đ</td></tr>
                <tr><td>Giảm trừ:</td><td align="right" style="color: #c0392b;">- {{DISCOUNT}} đ</td></tr>
                <tr style="font-weight: bold; font-size: 14px;">
                    <td style="border-top: 1px solid #000; padding-top: 5px;">TỔNG NGHĨA VỤ:</td>
                    <td align="right" style="border-top: 1px solid #000; padding-top: 5px;">{{TOTAL_AMOUNT}} đ</td>
                </tr>
                <tr><td style="color: #555;">Đã thanh toán:</td><td align="right" style="color: #27ae60;">{{PAID_AMOUNT}} đ</td></tr>
                <tr style="font-size: 15px; font-weight: bold; color: #c0392b;">
                    <td style="border-top: 2px solid #c0392b; padding-top: 8px;">DƯ NỢ CÒN LẠI:</td>
                    <td align="right" style="border-top: 2px solid #c0392b; padding-top: 8px;">{{REMAINING_AMOUNT}} đ</td>
                </tr>
            </table>

            <div style="margin-top: 20px; padding: 10px; border: 1px dashed #999; font-size: 11px; color: #555; text-align: center;">
                <strong>Xác nhận giao dịch điện tử:</strong> Phiếu thu này được xác thực tự động bởi hệ thống ngay sau khi nhận đủ tiền. Hai bên sử dụng bản sao này làm căn cứ chốt công nợ kỳ {{MONTH_YEAR}}.
            </div>
            </div>
        HTML;

        Setting::updateOrCreate(
            ['user_id' => null, 'key' => 'contract_template'],
            ['value' => $defaultContract]
        );

        Setting::updateOrCreate(
            ['user_id' => null, 'key' => 'invoice_template'],
            ['value' => $defaultInvoiceTemplate]
        );
    }
}