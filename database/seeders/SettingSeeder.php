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

        Setting::updateOrCreate(
            ['user_id' => null, 'key' => 'contract_template'],
            ['value' => $defaultContract]
        );
    }
}