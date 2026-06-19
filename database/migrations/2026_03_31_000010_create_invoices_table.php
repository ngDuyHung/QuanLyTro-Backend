<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng invoices - Hóa đơn / công nợ của hợp đồng thuê.
     *
     * Vai trò nghiệp vụ:
     * - Lưu khoản phải thu của một hợp đồng thuê trong một kỳ.
     * - Một hóa đơn có nhiều dòng chi tiết ở bảng invoice_items.
     * - Tiền khách trả thực tế KHÔNG lưu trực tiếp ở bảng này,
     *   mà lưu ở financial_transactions và được cấn vào hóa đơn qua
     *   financial_transaction_allocations.
     *
     * Lưu ý quan trọng:
     * - total_amount là số tiền phải thu.
     * - paid_amount là tổng số tiền đã được cấn vào hóa đơn.
     * - remaining_amount là số tiền còn nợ.
     * - status được cập nhật theo paid_amount / remaining_amount.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id()
                ->comment('ID hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Liên kết nghiệp vụ chính
             |--------------------------------------------------------------------------
             |
             | Mỗi hóa đơn thuộc về một hợp đồng thuê.
             | lease_id là khóa chính để biết hóa đơn này của khách/phòng nào.
             |
             */
            $table->foreignId('lease_id')
                ->comment('FK leases - hóa đơn thuộc hợp đồng thuê nào')
                ->constrained('leases')
                ->restrictOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Snapshot khu nhà và phòng tại thời điểm lập hóa đơn
             |--------------------------------------------------------------------------
             |
             | Về lý thuyết có thể lấy property_id, room_id thông qua:
             | invoice -> lease -> room -> property.
             |
             | Tuy nhiên vẫn nên lưu trực tiếp property_id và room_id để:
             | - lọc danh sách hóa đơn nhanh hơn,
             | - báo cáo doanh thu theo khu/phòng nhanh hơn,
             | - giữ đúng thông tin phòng tại thời điểm lập hóa đơn nếu sau này
             |   có nghiệp vụ chuyển phòng hoặc dữ liệu lease thay đổi.
             |
             */
            $table->foreignId('property_id')
                ->comment('FK properties - khu nhà tại thời điểm lập hóa đơn, dùng để lọc và báo cáo nhanh')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->foreignId('room_id')
                ->comment('FK rooms - phòng tại thời điểm lập hóa đơn, dùng để lọc và báo cáo nhanh')
                ->constrained('rooms')
                ->restrictOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Mã hóa đơn
             |--------------------------------------------------------------------------
             |
             | Mã hóa đơn nên do backend tự sinh, không nên để FE nhập tay.
             | Ví dụ: HD-202606-P101-0001 hoặc HD-2-202606-0964.
             |
             */
            $table->string('invoice_code', 50)
                ->unique()
                ->comment('Mã hóa đơn duy nhất, do hệ thống tự sinh');

            /*
             |--------------------------------------------------------------------------
             | Loại hóa đơn
             |--------------------------------------------------------------------------
             |
             | monthly    : hóa đơn hàng tháng.
             | checkin    : hóa đơn lúc nhận phòng, nếu cần thu tiền đầu kỳ.
             | checkout   : hóa đơn thanh lý/trả phòng.
             | adjustment : hóa đơn điều chỉnh/phát sinh khác.
             |
             */
            $table->enum('invoice_type', [
                    'monthly',
                    'checkin',
                    'checkout',
                    'adjustment',
                ])
                ->default('monthly')
                ->comment('Loại hóa đơn: monthly/checkin/checkout/adjustment');

            /*
             |--------------------------------------------------------------------------
             | Kỳ hóa đơn
             |--------------------------------------------------------------------------
             |
             | period_from và period_to xác định khoảng thời gian hóa đơn áp dụng.
             | Ví dụ: 2026-06-19 đến 2026-07-18.
             |
             | Ràng buộc period_to > period_from nên kiểm tra ở FormRequest/Service.
             |
             */
            $table->date('period_from')
                ->comment('Ngày bắt đầu kỳ hóa đơn');

            $table->date('period_to')
                ->comment('Ngày kết thúc kỳ hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Ngày phát hành và hạn thanh toán
             |--------------------------------------------------------------------------
             |
             | issue_date:
             | - Ngày hóa đơn được phát hành cho khách.
             | - Có thể NULL khi hóa đơn còn ở trạng thái draft.
             |
             | due_date:
             | - Hạn thanh toán.
             | - Dùng để chuyển trạng thái overdue nếu quá hạn chưa trả đủ.
             |
             */
            $table->date('issue_date')
                ->nullable()
                ->comment('Ngày phát hành hóa đơn cho khách, NULL nếu còn nháp');

            $table->date('due_date')
                ->nullable()
                ->comment('Hạn thanh toán hóa đơn, dùng để xác định quá hạn');

            /*
             |--------------------------------------------------------------------------
             | Trạng thái hóa đơn
             |--------------------------------------------------------------------------
             |
             | draft:
             | - Hóa đơn mới tạo, chủ trọ còn có thể chỉnh sửa.
             | - Người thuê chưa nhất thiết nhìn thấy.
             |
             | issued:
             | - Hóa đơn đã phát hành.
             | - Dữ liệu nên bị khóa chỉnh sửa trực tiếp.
             |
             | partially_paid:
             | - Khách đã trả một phần.
             |
             | paid:
             | - Khách đã trả đủ.
             |
             | overdue:
             | - Hóa đơn đã quá hạn thanh toán và còn nợ.
             |
             | cancelled:
             | - Hóa đơn bị hủy, không còn tính công nợ.
             |
             */
            $table->enum('status', [
                    'draft',
                    'issued',
                    'partially_paid',
                    'paid',
                    'overdue',
                    'cancelled',
                ])
                ->default('draft')
                ->index()
                ->comment('Trạng thái hóa đơn: draft/issued/partially_paid/paid/overdue/cancelled');

            /*
             |--------------------------------------------------------------------------
             | Các cột tiền
             |--------------------------------------------------------------------------
             |
             | Tất cả tiền lưu bằng số nguyên VND để tránh lỗi làm tròn.
             |
             | subtotal_amount:
             | - Tổng các dòng chi tiết hiện tại của hóa đơn.
             |
             | previous_debt_amount:
             | - Công nợ cũ được cộng dồn vào hóa đơn này nếu có.
             |
             | discount_amount:
             | - Tổng tiền giảm trừ.
             |
             | surcharge_amount:
             | - Tổng phụ thu/phát sinh cộng thêm.
             |
             | total_amount:
             | - Tổng phải thu cuối cùng.
             | - Công thức gợi ý:
             |   total_amount = subtotal_amount
             |                + previous_debt_amount
             |                + surcharge_amount
             |                - discount_amount
             |
             | paid_amount:
             | - Tổng số tiền đã được cấn từ financial_transactions vào hóa đơn.
             |
             | remaining_amount:
             | - Số tiền còn nợ.
             | - Công thức:
             |   remaining_amount = total_amount - paid_amount
             |
             */
            $table->unsignedBigInteger('subtotal_amount')
                ->default(0)
                ->comment('Tổng tiền các dòng chi tiết hóa đơn, chưa tính nợ cũ/giảm trừ/phụ thu');

            $table->unsignedBigInteger('previous_debt_amount')
                ->default(0)
                ->comment('Công nợ cũ được cộng vào hóa đơn kỳ này nếu có');

            $table->unsignedBigInteger('discount_amount')
                ->default(0)
                ->comment('Tổng tiền giảm trừ của hóa đơn');

            $table->unsignedBigInteger('surcharge_amount')
                ->default(0)
                ->comment('Tổng tiền phụ thu/phát sinh cộng thêm');

            $table->unsignedBigInteger('total_amount')
                ->default(0)
                ->comment('Tổng số tiền phải thu cuối cùng của hóa đơn');

            $table->unsignedBigInteger('paid_amount')
                ->default(0)
                ->comment('Tổng số tiền đã thanh toán/cấn trừ vào hóa đơn');

            $table->unsignedBigInteger('remaining_amount')
                ->default(0)
                ->comment('Số tiền còn nợ của hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Các mốc thời gian nghiệp vụ
             |--------------------------------------------------------------------------
             |
             | issued_at:
             | - Thời điểm hệ thống/chủ trọ phát hành hóa đơn.
             |
             | locked_at:
             | - Thời điểm hóa đơn bị khóa chỉnh sửa trực tiếp.
             | - Thường bằng issued_at hoặc sau khi có thanh toán.
             |
             | cancelled_at:
             | - Thời điểm hủy hóa đơn.
             |
             */
            $table->timestamp('issued_at')
                ->nullable()
                ->comment('Thời điểm phát hành hóa đơn');

            $table->timestamp('locked_at')
                ->nullable()
                ->comment('Thời điểm khóa hóa đơn, không cho sửa trực tiếp các dòng tiền');

            $table->timestamp('cancelled_at')
                ->nullable()
                ->comment('Thời điểm hủy hóa đơn');

            $table->string('cancel_reason', 255)
                ->nullable()
                ->comment('Lý do hủy hóa đơn nếu trạng thái là cancelled');

            /*
             |--------------------------------------------------------------------------
             | Ghi chú và người tạo
             |--------------------------------------------------------------------------
             */
            $table->text('note')
                ->nullable()
                ->comment('Ghi chú nội bộ cho hóa đơn');

            $table->foreignId('created_by')
                ->nullable()
                ->comment('FK users - người tạo hóa đơn, thường là chủ trọ')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Ràng buộc chống tạo trùng hóa đơn
             |--------------------------------------------------------------------------
             |
             | Một hợp đồng không nên có 2 hóa đơn cùng loại trong cùng một kỳ.
             | Ví dụ:
             | - Không được tạo 2 hóa đơn monthly cho cùng lease_id, cùng kỳ tháng 06.
             | - Nhưng vẫn có thể có 1 monthly và 1 adjustment cùng kỳ nếu nghiệp vụ cần.
             |
             */
            $table->unique(
                ['lease_id', 'period_from', 'period_to', 'invoice_type'],
                'uq_invoice_lease_period_type'
            );

            /*
             |--------------------------------------------------------------------------
             | Index hỗ trợ truy vấn
             |--------------------------------------------------------------------------
             |
             | property_id + status:
             | - Lọc hóa đơn theo khu nhà và trạng thái.
             |
             | room_id + period_from + period_to:
             | - Tra cứu hóa đơn của một phòng theo kỳ.
             |
             | lease_id + status:
             | - Tra cứu công nợ/hóa đơn theo hợp đồng.
             |
             | due_date:
             | - Tìm hóa đơn quá hạn.
             |
             */
            $table->index(['property_id', 'status'], 'idx_invoices_property_status');
            $table->index(['room_id', 'period_from', 'period_to'], 'idx_invoices_room_period');
            $table->index(['lease_id', 'status'], 'idx_invoices_lease_status');
            $table->index('due_date', 'idx_invoices_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};