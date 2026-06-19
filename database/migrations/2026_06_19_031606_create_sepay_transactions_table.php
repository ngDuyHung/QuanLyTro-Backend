<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng sepay_transactions - Giao dịch ngân hàng nhận từ SePay webhook.
     *
     * Vai trò nghiệp vụ:
     * - Lưu lại giao dịch gốc mà SePay gửi về qua webhook.
     * - Dùng để đối soát giao dịch ngân hàng với sổ thu chi của hệ thống.
     * - Không thay thế bảng financial_transactions.
     *
     * Phân biệt quan trọng:
     * - sepay_transactions:
     *   Lưu dữ liệu thô từ SePay/ngân hàng.
     *
     * - financial_transactions:
     *   Lưu sổ thu chi chính thức của hệ thống.
     *
     * Ví dụ:
     * - SePay gửi webhook báo tài khoản nhận 1.750.000đ.
     * - Hệ thống lưu 1 dòng vào sepay_transactions.
     * - Nếu match được hóa đơn, hệ thống tạo 1 dòng financial_transactions.
     * - Sau đó tạo financial_transaction_allocations để cấn tiền vào hóa đơn.
     *
     * Lưu ý:
     * - Bảng này nên giữ raw_payload để sau này cần kiểm tra lại dữ liệu gốc.
     * - Không nên xóa vật lý giao dịch SePay, chỉ đổi match_status nếu cần.
     */
    public function up(): void
    {
        Schema::create('sepay_transactions', function (Blueprint $table): void {
            $table->id()
                ->comment('ID giao dịch SePay trong hệ thống');

            /*
             |--------------------------------------------------------------------------
             | Tài khoản ngân hàng nhận/chuyển tiền
             |--------------------------------------------------------------------------
             |
             | bank_account_id:
             | - Liên kết với tài khoản ngân hàng của chủ trọ trong hệ thống.
             | - Có thể NULL nếu webhook gửi về nhưng hệ thống chưa map được
             |   accountNumber với bảng bank_accounts.
             |
             */
            $table->foreignId('bank_account_id')
                ->nullable()
                ->comment('FK bank_accounts - tài khoản ngân hàng liên quan, NULL nếu chưa map được')
                ->constrained('bank_accounts')
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Định danh giao dịch từ SePay/ngân hàng
             |--------------------------------------------------------------------------
             |
             | provider_transaction_id:
             | - ID giao dịch do SePay gửi về.
             | - Trong payload mẫu của SePay thường có field id.
             | - Dùng để chống xử lý trùng webhook.
             |
             | reference_code:
             | - Mã tham chiếu giao dịch ngân hàng.
             | - Trong payload mẫu thường là referenceCode.
             |
             * gateway:
             | - Tên ngân hàng/cổng giao dịch.
             | - Ví dụ: MBBank, Vietcombank...
             |
             */
            $table->string('provider_transaction_id', 100)
                ->nullable()
                ->comment('ID giao dịch do SePay cung cấp, dùng chống trùng webhook');

            $table->string('reference_code', 100)
                ->nullable()
                ->comment('Mã tham chiếu giao dịch ngân hàng, lấy từ referenceCode nếu có');

            $table->string('gateway', 100)
                ->nullable()
                ->comment('Tên ngân hàng/cổng giao dịch, ví dụ MBBank/Vietcombank');

            /*
             |--------------------------------------------------------------------------
             | Thời gian giao dịch
             |--------------------------------------------------------------------------
             |
             | transaction_time:
             | - Thời điểm giao dịch phát sinh tại ngân hàng.
             | - Lấy từ transactionDate của webhook nếu có.
             |
             | received_at:
             | - Thời điểm hệ thống của mình nhận webhook.
             |
             | processed_at:
             | - Thời điểm hệ thống xử lý match xong.
             |
             */
            $table->dateTime('transaction_time')
                ->nullable()
                ->comment('Thời điểm giao dịch phát sinh tại ngân hàng, lấy từ transactionDate');

            $table->timestamp('received_at')
                ->useCurrent()
                ->comment('Thời điểm hệ thống nhận webhook từ SePay');

            $table->timestamp('processed_at')
                ->nullable()
                ->comment('Thời điểm hệ thống xử lý/match giao dịch xong');

            /*
             |--------------------------------------------------------------------------
             | Thông tin tài khoản
             |--------------------------------------------------------------------------
             |
             | account_number:
             | - Số tài khoản ngân hàng nhận/chuyển tiền.
             |
             | sub_account:
             | - Tài khoản phụ/tài khoản ảo nếu SePay/ngân hàng có gửi.
             |
             */
            $table->string('account_number', 50)
                ->nullable()
                ->comment('Số tài khoản ngân hàng trong webhook');

            $table->string('sub_account', 50)
                ->nullable()
                ->comment('Tài khoản phụ/tài khoản ảo nếu có');

            /*
             |--------------------------------------------------------------------------
             | Nội dung giao dịch
             |--------------------------------------------------------------------------
             |
             | code:
             | - Mã thanh toán nếu SePay tách được.
             | - Có thể NULL tùy payload.
             |
             | content:
             | - Nội dung chuyển khoản gốc.
             | - Đây là phần quan trọng để hệ thống tìm mã hóa đơn.
             |
             | description:
             | - Mô tả giao dịch từ SePay/ngân hàng.
             |
             | matched_payment_code:
             | - Mã hệ thống parse được từ content.
             | - Ví dụ content = "Thanh toan HD-202606-0001"
             |   thì matched_payment_code = "HD-202606-0001".
             |
             */
            $table->string('code', 100)
                ->nullable()
                ->comment('Mã thanh toán do SePay tách được nếu có');

            $table->string('content', 500)
                ->nullable()
                ->comment('Nội dung chuyển khoản gốc từ ngân hàng/SePay');

            $table->string('description', 500)
                ->nullable()
                ->comment('Mô tả giao dịch từ SePay/ngân hàng');

            $table->string('matched_payment_code', 100)
                ->nullable()
                ->comment('Mã hóa đơn/mã thanh toán hệ thống parse được từ nội dung chuyển khoản');

            /*
             |--------------------------------------------------------------------------
             | Chiều tiền và số tiền
             |--------------------------------------------------------------------------
             |
             | transfer_type:
             | - in  : tiền vào tài khoản.
             | - out : tiền ra tài khoản.
             |
             | Với nghiệp vụ thanh toán hóa đơn, thường chỉ xử lý transfer_type = in.
             |
             | transfer_amount:
             | - Số tiền giao dịch.
             | - Lưu VND dạng số nguyên.
             |
             | accumulated:
             | - Số dư lũy kế nếu SePay/ngân hàng gửi về.
             | - Không bắt buộc dùng cho nghiệp vụ, nhưng lưu lại để đối chiếu.
             |
             */
            $table->enum('transfer_type', [
                    'in',
                    'out',
                ])
                ->default('in')
                ->comment('Loại giao dịch: in là tiền vào, out là tiền ra');

            $table->unsignedBigInteger('transfer_amount')
                ->default(0)
                ->comment('Số tiền giao dịch từ webhook SePay');

            $table->unsignedBigInteger('accumulated')
                ->default(0)
                ->comment('Số dư lũy kế nếu webhook có gửi, không bắt buộc dùng');

            /*
             |--------------------------------------------------------------------------
             | Trạng thái đối soát
             |--------------------------------------------------------------------------
             |
             | unmatched:
             | - Đã nhận webhook nhưng chưa match được hóa đơn/khoản thu.
             |
             | matched:
             | - Đã match toàn bộ số tiền sang financial_transactions.
             |
             | partially_matched:
             | - Chỉ match được một phần số tiền.
             | - Ví dụ khách chuyển 2.000.000 nhưng hệ thống mới cấn được 1.750.000.
             |
             | duplicated:
             | - Webhook/giao dịch bị trùng.
             |
             | ignored:
             | - Giao dịch bỏ qua, ví dụ transfer_type = out hoặc không thuộc nghiệp vụ.
             |
             | need_review:
             | - Cần chủ trọ kiểm tra thủ công.
             |
             */
            $table->enum('match_status', [
                    'unmatched',
                    'matched',
                    'partially_matched',
                    'duplicated',
                    'ignored',
                    'need_review',
                ])
                ->default('unmatched')
                ->index()
                ->comment('Trạng thái đối soát giao dịch SePay');

            /*
             |--------------------------------------------------------------------------
             | Số tiền đã match
             |--------------------------------------------------------------------------
             |
             | matched_amount:
             | - Tổng số tiền đã được tạo thành financial_transactions từ giao dịch này.
             | - Nếu matched_amount = transfer_amount thì match_status thường là matched.
             | - Nếu matched_amount < transfer_amount thì có thể là partially_matched.
             |
             */
            $table->unsignedBigInteger('matched_amount')
                ->default(0)
                ->comment('Số tiền đã được đối soát/tạo vào sổ thu chi');

            /*
             |--------------------------------------------------------------------------
             | Lỗi xử lý nếu có
             |--------------------------------------------------------------------------
             |
             | error_message:
             | - Lưu lỗi khi xử lý webhook thất bại.
             | - Ví dụ: không tìm thấy hóa đơn, sai định dạng nội dung, trùng giao dịch...
             |
             */
            $table->text('error_message')
                ->nullable()
                ->comment('Thông báo lỗi khi xử lý/match webhook nếu có');

            /*
             |--------------------------------------------------------------------------
             | Payload gốc
             |--------------------------------------------------------------------------
             |
             | raw_payload:
             | - Lưu toàn bộ dữ liệu gốc SePay gửi về.
             | - Rất quan trọng để debug/đối chiếu khi phát sinh sai lệch.
             |
             */
            $table->json('raw_payload')
                ->nullable()
                ->comment('Payload gốc từ SePay webhook để phục vụ debug và đối soát');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Unique và Index
             |--------------------------------------------------------------------------
             |
             | provider_transaction_id:
             | - Chống xử lý trùng cùng một webhook/giao dịch.
             |
             | reference_code:
             | - Tra cứu nhanh theo mã giao dịch ngân hàng.
             |
             | code:
             | - Tra cứu nhanh theo mã thanh toán do SePay tách được.
             |
             | matched_payment_code:
             | - Tra cứu nhanh theo mã hóa đơn/mã thanh toán parse từ nội dung.
             |
             | match_status + received_at:
             | - Lọc danh sách giao dịch chưa match/cần xử lý theo thời gian.
             |
             | account_number + transaction_time:
             | - Đối soát giao dịch theo tài khoản và thời gian.
             */
            $table->unique(
                'provider_transaction_id',
                'uq_sepay_provider_transaction_id'
            );

            $table->index('reference_code', 'idx_sepay_reference_code');
            $table->index('code', 'idx_sepay_code');
            $table->index('matched_payment_code', 'idx_sepay_matched_payment_code');
            $table->index(['match_status', 'received_at'], 'idx_sepay_match_status_received_at');
            $table->index(['account_number', 'transaction_time'], 'idx_sepay_account_transaction_time');
            $table->index(['transfer_type', 'transaction_time'], 'idx_sepay_transfer_type_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sepay_transactions');
    }
};