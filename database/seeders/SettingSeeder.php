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
        <div style="font-family: 'DejaVu Sans', sans-serif; font-size: 14px; line-height: 1.5; color: #000; padding: 20px;">
            <h2 style="text-align: center; font-weight: bold; margin-bottom: 5px; font-size: 16px;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h2>
            <h3 style="text-align: center; font-weight: bold; margin-top: 0; font-size: 15px; text-decoration: underline;">Độc lập - Tự do - Hạnh phúc</h3>

            <h2 style="text-align: center; margin-top: 30px; margin-bottom: 15px; font-size: 18px; font-weight: bold;">HỢP ĐỒNG THUÊ PHÒNG TRỌ</h2>
            <p style="text-align: center; font-style: italic; margin-bottom: 30px;">Hôm nay, ngày {{CURRENT_DAY}} tháng {{CURRENT_MONTH}} năm {{CURRENT_YEAR}}, tại {{PROPERTY_ADDRESS}}.</p>

            <p style="font-style: italic; margin-bottom: 5px;">- Căn cứ Bộ luật Dân sự số 91/2015/QH13 Quốc hội thông qua ngày 24/11/2015;</p>
            <p style="font-style: italic; margin-bottom: 5px;">- Căn cứ Luật Nhà ở số 27/2023/QH15 Quốc hội thông qua ngày 27/11/2023;</p>
            <p style="font-style: italic; margin-bottom: 20px;">- Căn cứ nhu cầu và khả năng của hai bên.</p>

            <p>Chúng tôi gồm có:</p>

            <p><strong>BÊN CHO THUÊ NHÀ (BÊN A):</strong></p>
            <p>- Ông/Bà: <span style="text-transform: uppercase; font-weight: bold;">{{LANDLORD_NAME}}</span></p>
            <p>- Số điện thoại: {{LANDLORD_PHONE}}</p>
            <p>- Là chủ sở hữu/người đại diện hợp pháp của khu trọ: <strong>{{PROPERTY_NAME}}</strong></p>

            <p style="margin-top: 15px;"><strong>BÊN THUÊ NHÀ (BÊN B):</strong></p>
            <p>- Ông/Bà: <strong><span style="text-transform: uppercase;">{{TENANT_NAME}}</span></strong></p>
            <p>- Số CMND/CCCD: {{TENANT_CCCD}}</p>
            <p>- Số điện thoại: {{TENANT_PHONE}}</p>

            <p style="margin-top: 20px;">Sau khi bàn bạc, hai bên tự nguyện ký kết Hợp đồng thuê phòng trọ với các điều khoản dưới đây:</p>

            <h4 style="font-size: 14px; margin-top: 20px; font-weight: bold;">ĐIỀU 1: ĐỐI TƯỢNG VÀ MỤC ĐÍCH THUÊ</h4>
            <p>1.1. Bên A đồng ý cho Bên B thuê phòng số <strong>{{ROOM_NAME}}</strong> thuộc khu nhà <strong>{{PROPERTY_NAME}}</strong> tại địa chỉ {{PROPERTY_ADDRESS}}.</p>
            <p>1.2. Mục đích thuê: Sử dụng để ở và sinh hoạt hợp pháp.</p>
            <p>1.3. Ngày bắt đầu tính tiền thuê: {{START_DATE}}</p>

            <h4 style="font-size: 14px; margin-top: 20px; font-weight: bold;">ĐIỀU 2: GIÁ THUÊ, TIỀN THẾ CHÂN VÀ THANH TOÁN</h4>
            <p>2.1. Giá thuê phòng: <strong>{{ROOM_PRICE}} VNĐ/tháng</strong>. Tiền phòng được thanh toán trả trước vào đầu mỗi chu kỳ.</p>
            <p>2.2. Tiền thế chân (đặt cọc): <strong>{{DEPOSIT}} VNĐ</strong>. Theo quy định của pháp luật dân sự, khoản tiền này nhằm bảo đảm thực hiện nghĩa vụ hợp đồng. Bên A sẽ hoàn trả cho Bên B sau khi chấm dứt hợp đồng hợp pháp và Bên B đã thanh toán đầy đủ các khoản phát sinh, nợ đọng (nếu có).</p>
            <p>2.3. Các chi phí dịch vụ khác áp dụng tại thời điểm ký hợp đồng:</p>
            {{SERVICES_LIST}}

            <h4 style="font-size: 14px; margin-top: 20px; font-weight: bold;">ĐIỀU 3: QUYỀN VÀ NGHĨA VỤ CỦA CÁC BÊN</h4>
            <p>3.1. <strong>Bên A</strong> có trách nhiệm bàn giao phòng đúng hiện trạng, đảm bảo quyền sử dụng ổn định cho Bên B và hỗ trợ thủ tục lưu trú theo pháp luật.</p>
            <p>3.2. <strong>Bên B</strong> có trách nhiệm đóng tiền đầy đủ, đúng hạn; sử dụng phòng đúng mục đích; tuân thủ nghiêm ngặt các quy định về an ninh trật tự, phòng cháy chữa cháy của Nhà nước và nội quy khu trọ.</p>

            <h4 style="font-size: 14px; margin-top: 20px; font-weight: bold;">ĐIỀU 4: CHẤM DỨT HỢP ĐỒNG VÀ XỬ LÝ VI PHẠM</h4>
            <p>4.1. Đây là hợp đồng thuê không ràng buộc thời hạn cứng. Tuy nhiên, khi Bên B muốn chấm dứt hợp đồng, Bên B <strong>bắt buộc phải thông báo cho Bên A trước ít nhất 10 ngày</strong> tính đến ngày thực tế bàn giao phòng.</p>
            <p>4.2. Trường hợp Bên B đơn phương trả phòng mà vi phạm thời gian báo trước (dưới 10 ngày), Bên B sẽ bị khấu trừ toàn bộ tiền thế chân nhằm bồi thường thiệt hại cho Bên A.</p>
            <p>4.3. Tiền phòng tháng cuối được chốt theo quy định riêng của khu trọ: Nếu ngày trả phòng rơi vào chu kỳ mới, từ 1 đến 5 ngày sử dụng vẫn sẽ bị tính phí tròn 1 tháng tiền nhà.</p>

            <h4 style="font-size: 14px; margin-top: 20px; font-weight: bold;">ĐIỀU 5: ĐIỀU KHOẢN THI HÀNH</h4>
            <p>5.1. Hai bên cam kết thực hiện đúng các điều khoản đã thỏa thuận. Những vấn đề chưa được quy định sẽ được giải quyết dựa trên Luật Nhà ở 2023 và Bộ luật Dân sự 2015.</p>
            <p>5.2. Hợp đồng được lập dưới dạng dữ liệu điện tử lưu trữ trên hệ thống phần mềm quản lý, có giá trị pháp lý kể từ ngày {{START_DATE}}.</p>

            <table style="width: 100%; margin-top: 40px; border: none; text-align: center;">
                <tbody>
                    <tr>
                        <td style="width: 50%;">
                            <strong>ĐẠI DIỆN BÊN A</strong><br>
                            <em>(Ký, ghi rõ họ tên)</em><br><br><br><br><br>
                            <strong>{{LANDLORD_NAME}}</strong>
                        </td>
                        <td style="width: 50%;">
                            <strong>ĐẠI DIỆN BÊN B</strong><br>
                            <em>(Ký, ghi rõ họ tên)</em><br><br><br><br><br>
                            <strong>{{TENANT_NAME}}</strong>
                        </td>
                    </tr>
                </tbody>
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
