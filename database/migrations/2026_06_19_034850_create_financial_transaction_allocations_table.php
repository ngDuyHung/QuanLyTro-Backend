<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng financial_transaction_allocations - Cấn tiền thu chi vào hóa đơn.
     *
     * Vai trò nghiệp vụ:
     * - Liên kết một khoản thu trong financial_transactions với một hoặc nhiều hóa đơn.
     * - Cho biết khoản tiền đã thu được dùng để trừ vào hóa đơn nào, bao nhiêu tiền.
     *
     * Vì sao cần bảng này?
     * - financial_transactions chỉ nói rằng hệ thống đã thu/chi một khoản tiền.
     * - invoices chỉ nói khách đang nợ bao nhiêu.
     * - Bảng này nằm giữa để nói:
     *   "Khoản thu A được dùng để trả cho hóa đơn B số tiền X".
     *
     * Ví dụ 1: Khách trả lắt nhắt cho một hóa đơn.
     * - HD001 tổng 1.750.000.
     * - Lần 1 trả 500.000.
     * - Lần 2 trả 700.000.
     * - Lần 3 trả 550.000.
     *
     * Khi đó:
     * - financial_transactions có 3 dòng thu tiền.
     * - financial_transaction_allocations có 3 dòng cấn vào cùng HD001.
     *
     * Ví dụ 2: Một lần chuyển khoản trả nhiều hóa đơn.
     * - Khách chuyển 2.000.000.
     * - Cấn 500.000 vào hóa đơn tháng 5.
     * - Cấn 1.500.000 vào hóa đơn tháng 6.
     *
     * Khi đó:
     * - financial_transactions có 1 dòng thu tiền 2.000.000.
     * - financial_transaction_allocations có 2 dòng phân bổ.
     *
     * Lưu ý bảo toàn dữ liệu tài chính:
     * - Không nên xóa vật lý giao dịch đã cấn tiền.
     * - Nếu sai thì nên tạo giao dịch điều chỉnh hoặc hủy trạng thái ở Service.
     */
    public function up(): void
    {
        Schema::create('financial_transaction_allocations', function (Blueprint $table): void {
            $table->id()
                ->comment('ID bản ghi cấn trừ tiền vào hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Giao dịch thu chi
             |--------------------------------------------------------------------------
             |
             | financial_transaction_id:
             | - Trỏ đến dòng tiền thực tế trong bảng financial_transactions.
             | - Thường là khoản thu direction = income, category = invoice_payment.
             |
             | Không cascade delete để tránh mất lịch sử tài chính.
             | Nếu muốn hủy giao dịch, nên đổi status của financial_transactions.
             |
             */
            $table->foreignId('financial_transaction_id')
                ->comment('FK financial_transactions - khoản thu/chi được dùng để cấn vào hóa đơn')
                // Thêm 'id' (tên cột đích) và 'fk_fta_trans_id' (tên khóa ngoại rút gọn)
                ->constrained('financial_transactions', 'id', 'fk_fta_trans_id')
                ->restrictOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Hóa đơn được cấn tiền
             |--------------------------------------------------------------------------
             |
             | invoice_id:
             | - Hóa đơn nhận khoản tiền cấn trừ.
             |
             | Không cascade delete để tránh mất lịch sử công nợ/thanh toán.
             | Nếu hóa đơn đã có allocation thì nghiệp vụ nên chặn xóa hóa đơn.
             |
             */
            $table->foreignId('invoice_id')
                ->comment('FK invoices - hóa đơn được cấn tiền')
                ->constrained('invoices')
                ->restrictOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Số tiền cấn vào hóa đơn
             |--------------------------------------------------------------------------
             |
             | allocated_amount:
             | - Số tiền từ financial_transactions được dùng để trừ vào invoice.
             |
             | Quy tắc kiểm tra ở Service:
             | - Tổng allocated_amount của một financial_transaction
             |   không được vượt quá financial_transactions.amount.
             |
             | - Tổng allocated_amount của một invoice
             |   không được vượt quá invoices.total_amount hoặc invoices.remaining_amount
             |   tùy logic xử lý.
             |
             */
            $table->unsignedBigInteger('allocated_amount')
                ->comment('Số tiền được cấn từ giao dịch thu chi vào hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Loại cấn trừ
             |--------------------------------------------------------------------------
             |
             | payment:
             | - Cấn tiền khách trả vào hóa đơn.
             |
             | refund:
             | - Dùng nếu sau này cần ghi nhận hoàn/đảo một phần allocation.
             |
             | adjustment:
             | - Điều chỉnh công nợ đặc biệt.
             |
             | Hiện tại nghiệp vụ chính sẽ dùng payment.
             |
             */
            $table->enum('allocation_type', [
                'payment',
                'refund',
                'adjustment',
            ])
                ->default('payment')
                ->comment('Loại cấn trừ: payment/refund/adjustment');

            /*
             |--------------------------------------------------------------------------
             | Thời điểm cấn tiền
             |--------------------------------------------------------------------------
             |
             | allocated_at:
             | - Thời điểm hệ thống ghi nhận khoản tiền này được trừ vào hóa đơn.
             | - Thường bằng thời điểm tạo giao dịch thu chi hoặc thời điểm đối soát xong.
             |
             */
            $table->timestamp('allocated_at')
                ->useCurrent()
                ->comment('Thời điểm cấn tiền vào hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Người thực hiện
             |--------------------------------------------------------------------------
             |
             | created_by:
             | - Với tiền mặt/chuyển khoản thủ công: là chủ trọ thao tác.
             | - Với SePay tự động: có thể NULL hoặc user chủ trọ nếu xác định được.
             |
             */
            $table->foreignId('created_by')
                ->nullable()
                ->comment('FK users - người thực hiện cấn tiền, NULL nếu hệ thống tự động')
                ->constrained('users')
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Ghi chú
             |--------------------------------------------------------------------------
             |
             | note:
             | - Dùng khi chủ trọ cần giải thích thao tác cấn tiền.
             | - Ví dụ: Khách trả thiếu, cấn trước vào hóa đơn cũ.
             |
             */
            $table->text('note')
                ->nullable()
                ->comment('Ghi chú cho thao tác cấn tiền nếu có');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Ràng buộc chống trùng
             |--------------------------------------------------------------------------
             |
             | Một financial_transaction không nên cấn vào cùng một invoice nhiều dòng.
             | Nếu cần sửa số tiền, nên update dòng allocation hoặc tạo giao dịch điều chỉnh.
             |
             */
            $table->unique(
                ['financial_transaction_id', 'invoice_id'],
                'uq_financial_transaction_invoice'
            );

            /*
             |--------------------------------------------------------------------------
             | Index hỗ trợ truy vấn
             |--------------------------------------------------------------------------
             |
             | invoice_id:
             | - Tính tổng số tiền đã cấn vào một hóa đơn.
             |
             | financial_transaction_id:
             | - Xem một khoản thu đã được phân bổ cho những hóa đơn nào.
             |
             | allocation_type:
             | - Lọc theo loại cấn trừ nếu sau này có refund/adjustment.
             |
             */
            $table->index('invoice_id', 'idx_allocations_invoice_id');
            $table->index('financial_transaction_id', 'idx_allocations_transaction_id');
            $table->index('allocation_type', 'idx_allocations_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transaction_allocations');
    }
};
