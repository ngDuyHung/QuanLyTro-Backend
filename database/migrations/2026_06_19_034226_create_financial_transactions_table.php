<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng financial_transactions - Sổ thu chi / dòng tiền thực tế.
     *
     * Vai trò nghiệp vụ:
     * - Lưu TẤT CẢ các khoản tiền thật sự vào/ra.
     * - Thay thế bảng payments cũ.
     * - Không chỉ lưu thanh toán hóa đơn, mà còn lưu:
     *   + thu tiền hóa đơn,
     *   + thu cọc giữ chỗ,
     *   + thu tiền thế chân,
     *   + tịch thu cọc,
     *   + hoàn thế chân,
     *   + thu phí hư hỏng,
     *   + chi sửa chữa,
     *   + chi vận hành,
     *   + thu/chi khác.
     *
     * Phân biệt quan trọng:
     * - invoices: khách phải trả bao nhiêu.
     * - financial_transactions: thực tế tiền vào/ra bao nhiêu.
     * - financial_transaction_allocations: khoản thu nào được cấn vào hóa đơn nào.
     *
     * Ví dụ:
     * - Khách trả 500.000đ tiền mặt cho hóa đơn HD001:
     *   -> tạo 1 dòng financial_transactions direction = income.
     *   -> tạo 1 dòng financial_transaction_allocations cấn 500.000đ vào HD001.
     *
     * - Khách đóng 1.000.000đ tiền thế chân:
     *   -> tạo 1 dòng financial_transactions category = security_deposit.
     *   -> không cần invoice_id, không cần allocation nếu không cấn vào hóa đơn.
     */
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->id()
                ->comment('ID giao dịch thu chi');

            /*
             |--------------------------------------------------------------------------
             | Phạm vi dữ liệu
             |--------------------------------------------------------------------------
             |
             | property_id:
             | - Bắt buộc để biết khoản thu/chi thuộc khu nhà nào.
             | - Dùng để lọc dữ liệu theo chủ trọ/khu nhà và làm báo cáo.
             |
             | room_id:
             | - Nullable vì có khoản thu/chi không gắn với phòng cụ thể.
             | - Ví dụ: chi vận hành chung cả khu.
             |
             | lease_id:
             | - Nullable vì có khoản thu/chi không gắn với hợp đồng.
             | - Ví dụ: chi sửa khu nhà chung.
             |
             | tenant_id:
             | - Nullable vì một số khoản chi không liên quan khách thuê.
             |
             */
            $table->foreignId('property_id')
                ->comment('FK properties - khu nhà phát sinh khoản thu/chi')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->foreignId('room_id')
                ->nullable()
                ->comment('FK rooms - phòng liên quan đến khoản thu/chi, NULL nếu là khoản chung')
                ->constrained('rooms')
                ->nullOnDelete();

            $table->foreignId('lease_id')
                ->nullable()
                ->comment('FK leases - hợp đồng liên quan đến khoản thu/chi, NULL nếu không gắn hợp đồng')
                ->constrained('leases')
                ->nullOnDelete();

            $table->foreignId('tenant_id')
                ->nullable()
                ->comment('FK tenants - khách thuê liên quan đến khoản thu/chi, NULL nếu không áp dụng')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->string('tenant_name_snapshot', 100)->nullable()
                ->comment('Lưu vết tên khách thuê tại thời điểm phát sinh giao dịch');

            /*
             |--------------------------------------------------------------------------
             | Tài khoản ngân hàng
             |--------------------------------------------------------------------------
             |
             | bank_account_id:
             | - Dùng khi khoản thu/chi đi qua tài khoản ngân hàng.
             | - NULL nếu thu/chi tiền mặt hoặc chưa xác định tài khoản.
             |
             */
            $table->foreignId('bank_account_id')
                ->nullable()
                ->comment('FK bank_accounts - tài khoản ngân hàng nhận/chuyển tiền, NULL nếu tiền mặt')
                ->constrained('bank_accounts')
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Giao dịch SePay
             |--------------------------------------------------------------------------
             |
             | sepay_transaction_id:
             | - Trỏ đến giao dịch gốc nhận từ webhook SePay.
             | - NULL nếu là tiền mặt, chuyển khoản thủ công hoặc khoản không qua SePay.
             |
             | Lưu ý:
             | - Bảng sepay_transactions phải được tạo trước bảng này.
             |
             */
            $table->foreignId('sepay_transaction_id')
                ->nullable()
                ->comment('FK sepay_transactions - giao dịch SePay gốc nếu khoản thu đến từ webhook')
                ->constrained('sepay_transactions')
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Mã giao dịch thu chi
             |--------------------------------------------------------------------------
             |
             | transaction_code là mã phiếu thu/phiếu chi do hệ thống tự sinh.
             |
             | Ví dụ:
             | - PT-202606-0001
             | - PC-202606-0001
             |
             */
            $table->string('transaction_code', 50)
                ->unique()
                ->comment('Mã giao dịch thu/chi duy nhất do hệ thống tự sinh');

            /*
             |--------------------------------------------------------------------------
             | Chiều tiền
             |--------------------------------------------------------------------------
             |
             | income:
             | - Tiền đi vào.
             | - Ví dụ: khách trả tiền hóa đơn, thu cọc, thu thế chân.
             |
             | expense:
             | - Tiền đi ra.
             | - Ví dụ: hoàn thế chân, chi sửa chữa, chi vận hành.
             |
             */
            $table->enum('direction', [
                'income',
                'expense',
            ])
                ->comment('Chiều dòng tiền: income là tiền vào, expense là tiền ra');

            /*
             |--------------------------------------------------------------------------
             | Loại nghiệp vụ thu chi
             |--------------------------------------------------------------------------
             |
             | invoice_payment:
             | - Thu tiền hóa đơn.
             |
             | holding_deposit:
             | - Thu cọc giữ chỗ.
             |
             | security_deposit:
             | - Thu tiền thế chân.
             |
             | deposit_forfeit:
             | - Tịch thu cọc giữ chỗ khi khách quá hạn không nhận phòng.
             |
             | refund_security_deposit:
             | - Hoàn tiền thế chân khi thanh lý/trả phòng.
             |
             | damage_fee:
             | - Thu phí hư hỏng/phạt phát sinh.
             |
             | repair:
             | - Chi sửa chữa phòng/khu nhà.
             |
             | operation:
             | - Chi phí vận hành chung.
             |
             | other_income:
             | - Khoản thu khác.
             |
             | other_expense:
             | - Khoản chi khác.
             |
             */
            $table->enum('category', [
                'invoice_payment',
                'holding_deposit',
                'security_deposit',
                'deposit_forfeit',
                'refund_security_deposit',
                'damage_fee',
                'repair',
                'operation',
                'other_income',
                'other_expense',
            ])
                ->comment('Loại nghiệp vụ thu/chi');

            /*
             |--------------------------------------------------------------------------
             | Bản chất kế toán
             |--------------------------------------------------------------------------
             |
             | revenue:
             | - Doanh thu thật sự.
             | - Ví dụ: tiền phòng, điện, nước, rác, internet, tịch thu cọc.
             |
             | liability_in:
             | - Tiền nhận vào nhưng bản chất là khoản phải trả lại.
             | - Ví dụ: tiền thế chân.
             |
             | liability_out:
             | - Tiền trả ra để hoàn khoản đang giữ hộ/giữ nợ.
             | - Ví dụ: hoàn thế chân.
             |
             | expense:
             | - Chi phí thật sự.
             | - Ví dụ: sửa chữa, vận hành.
             |
             | receivable_adjustment:
             | - Điều chỉnh công nợ, dùng khi cần xử lý nghiệp vụ đặc biệt.
             |
             */
            $table->enum('accounting_type', [
                'revenue',
                'liability_in',
                'liability_out',
                'expense',
                'receivable_adjustment',
            ])
                ->comment('Bản chất kế toán của khoản thu/chi');

            /*
             |--------------------------------------------------------------------------
             | Số tiền
             |--------------------------------------------------------------------------
             |
             | amount:
             | - Lưu số tiền VND bằng số nguyên.
             | - Không dùng decimal để tránh sai số làm tròn.
             | - Chiều tiền đã được xác định bởi direction nên amount luôn là số dương.
             |
             */
            $table->unsignedBigInteger('amount')
                ->comment('Số tiền giao dịch, lưu VND dạng số nguyên, luôn là số dương');

            /*
             |--------------------------------------------------------------------------
             | Phương thức thu/chi
             |--------------------------------------------------------------------------
             |
             | cash:
             | - Tiền mặt.
             |
             | bank_transfer:
             | - Chuyển khoản thủ công, không qua webhook SePay.
             |
             | sepay:
             | - Chuyển khoản được nhận tự động qua webhook SePay.
             |
             | other:
             | - Phương thức khác nếu sau này phát sinh.
             |
             */
            $table->enum('method', [
                'cash',
                'bank_transfer',
                'sepay',
                'other',
            ])
                ->default('cash')
                ->comment('Phương thức thu/chi: cash/bank_transfer/sepay/other');

            $table->string('proof_image', 500)->nullable()
                ->comment('Đường dẫn ảnh minh chứng chuyển khoản');
            /*
             |--------------------------------------------------------------------------
             | Trạng thái giao dịch
             |--------------------------------------------------------------------------
             |
             | pending:
             | - Giao dịch đang chờ xác nhận.
             | - Ví dụ: webhook nhận nhưng chưa match được hóa đơn.
             |
             | confirmed:
             | - Giao dịch đã xác nhận hợp lệ.
             |
             | cancelled:
             | - Giao dịch bị hủy.
             |
             | refunded:
             | - Giao dịch đã hoàn lại.
             |
             */
            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'refunded',
            ])
                ->default('confirmed')
                ->index()
                ->comment('Trạng thái giao dịch thu/chi');

            /*
             |--------------------------------------------------------------------------
             | Ngày giờ giao dịch
             |--------------------------------------------------------------------------
             |
             | transaction_date:
             | - Thời điểm thực tế phát sinh tiền.
             | - Với SePay thì lấy theo transactionDate từ webhook.
             | - Với tiền mặt thì lấy thời điểm chủ trọ ghi nhận.
             |
             */
            $table->dateTime('transaction_date')
                ->comment('Ngày giờ thực tế phát sinh giao dịch thu/chi');

            /*
             |--------------------------------------------------------------------------
             | Thông tin chuyển khoản
             |--------------------------------------------------------------------------
             |
             | transfer_content:
             | - Nội dung chuyển khoản.
             | - Ví dụ: HD001 P101.
             |
             | bank_transaction_code:
             | - Mã tham chiếu giao dịch ngân hàng.
             | - Với SePay có thể lấy từ referenceCode.
             |
             */
            $table->string('transfer_content', 255)
                ->nullable()
                ->comment('Nội dung chuyển khoản nếu có');

            $table->string('bank_transaction_code', 100)
                ->nullable()
                ->comment('Mã giao dịch/tham chiếu ngân hàng nếu có');

            /*
             |--------------------------------------------------------------------------
             | Các mốc xác nhận/hủy
             |--------------------------------------------------------------------------
             |
             | confirmed_at:
             | - Thời điểm giao dịch được xác nhận hợp lệ.
             |
             | cancelled_at:
             | - Thời điểm hủy giao dịch.
             |
             | cancel_reason:
             | - Lý do hủy giao dịch.
             |
             */
            $table->timestamp('confirmed_at')
                ->nullable()
                ->comment('Thời điểm xác nhận giao dịch hợp lệ');

            $table->timestamp('cancelled_at')
                ->nullable()
                ->comment('Thời điểm hủy giao dịch');

            $table->string('cancel_reason', 255)
                ->nullable()
                ->comment('Lý do hủy giao dịch nếu có');

            /*
             |--------------------------------------------------------------------------
             | Mô tả và ghi chú
             |--------------------------------------------------------------------------
             |
             | description:
             | - Nội dung ngắn gọn hiển thị ngoài danh sách.
             |
             | note:
             | - Ghi chú nội bộ dài hơn nếu cần.
             |
             */
            $table->string('description', 255)
                ->nullable()
                ->comment('Mô tả ngắn gọn khoản thu/chi');

            $table->text('note')
                ->nullable()
                ->comment('Ghi chú nội bộ cho giao dịch thu/chi');

            /*
             |--------------------------------------------------------------------------
             | Người tạo
             |--------------------------------------------------------------------------
             |
             | created_by:
             | - Với tiền mặt/chuyển khoản thủ công: là chủ trọ ghi nhận.
             | - Với SePay tự động: có thể NULL hoặc user chủ trọ nếu hệ thống xác định được.
             |
             */
            $table->foreignId('created_by')
                ->nullable()
                ->comment('FK users - người tạo/xác nhận giao dịch, NULL nếu hệ thống tự động ghi nhận')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Index hỗ trợ truy vấn
             |--------------------------------------------------------------------------
             |
             | property_id + transaction_date:
             | - Xem sổ thu chi theo khu nhà và khoảng thời gian.
             |
             | lease_id:
             | - Xem các khoản thu/chi liên quan đến một hợp đồng.
             |
             | room_id:
             | - Xem các khoản thu/chi liên quan đến một phòng.
             |
             | tenant_id:
             | - Xem lịch sử thu/chi của một khách thuê.
             |
             | category:
             | - Báo cáo theo loại thu/chi.
             |
             | method:
             | - Báo cáo theo phương thức tiền mặt/chuyển khoản/SePay.
             |
             | sepay_transaction_id:
             | - Đối soát giao dịch SePay với sổ thu chi.
             |
             */
            $table->index(['property_id', 'transaction_date'], 'idx_financial_property_date');
            $table->index('lease_id', 'idx_financial_lease_id');
            $table->index('room_id', 'idx_financial_room_id');
            $table->index('tenant_id', 'idx_financial_tenant_id');
            $table->index('category', 'idx_financial_category');
            $table->index('method', 'idx_financial_method');
            $table->index('sepay_transaction_id', 'idx_financial_sepay_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
