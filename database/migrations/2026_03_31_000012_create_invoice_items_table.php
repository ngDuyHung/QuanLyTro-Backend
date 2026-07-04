<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng invoice_items - Chi tiết hóa đơn.
     *
     * Vai trò nghiệp vụ:
     * - Mỗi hóa đơn có nhiều dòng chi tiết.
     * - Mỗi dòng thể hiện một khoản tính tiền cụ thể:
     *   tiền phòng, điện, nước, rác, internet, nợ cũ, phí hư hỏng,
     *   giảm trừ, phụ thu hoặc khoản khác.
     *
     * Ví dụ một hóa đơn có thể gồm:
     * - Tiền phòng tháng 06/2026: 1.500.000
     * - Điện 35 kWh x 3.500: 122.500
     * - Nước 8 m3, miễn 4 m3, tính tiền 4 m3 x 15.000: 60.000
     * - Rác: 70.000
     * - Internet: 100.000
     *
     * Lưu ý quan trọng:
     * - Giá tại thời điểm lập hóa đơn phải được snapshot lại.
     * - Không lấy lại giá hiện tại từ bảng service_prices để tính hóa đơn cũ,
     *   vì sau này chủ trọ có thể đổi giá.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id()
                ->comment('ID dòng chi tiết hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Liên kết hóa đơn
             |--------------------------------------------------------------------------
             |
             | Mỗi dòng chi tiết bắt buộc thuộc về một hóa đơn.
             | Khi xóa hóa đơn thì xóa luôn toàn bộ dòng chi tiết.
             |
             */
            $table->foreignId('invoice_id')
                ->comment('FK invoices - dòng chi tiết này thuộc hóa đơn nào')
                ->constrained('invoices')
                ->cascadeOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Liên kết bảng giá dịch vụ
             |--------------------------------------------------------------------------
             |
             | service_price_id dùng để biết dòng này được tính từ bảng giá nào.
             |
             | Hiện tại dự án của bạn đang có bảng service_prices nên giữ tên này.
             | Nếu sau này đổi sang bảng service_price_versions để quản lý lịch sử giá
             | chuẩn hơn thì có thể đổi cột này thành service_price_version_id.
             |
             | Cột này nullable vì:
             | - Một số dòng không đến từ bảng giá dịch vụ, ví dụ:
             |   nợ cũ, phí hư hỏng, giảm trừ, phụ thu, khoản khác.
             | - Nếu bảng giá bị xóa, hóa đơn cũ vẫn giữ được snapshot tiền.
             |
             */
            $table->foreignId('service_price_id')
                ->nullable()
                ->comment('FK service_prices - bảng giá được dùng để tạo dòng này, NULL nếu là khoản thủ công')
                ->constrained('service_prices')
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Loại khoản phí
             |--------------------------------------------------------------------------
             |
             | room:
             | - Tiền phòng.
             |
             | electricity:
             | - Tiền điện theo chỉ số kWh.
             |
             | water:
             | - Tiền nước theo chỉ số m3, có thể có miễn phí theo nhân khẩu.
             |
             | garbage:
             | - Tiền rác.
             |
             | internet:
             | - Tiền internet.
             |
             | previous_debt:
             | - Nợ cũ được cộng vào hóa đơn kỳ này.
             |
             | damage_fee:
             | - Phí hư hỏng/mất chìa khóa/vỡ kính/phạt phát sinh khi ở hoặc thanh lý.
             |
             | discount:
             | - Khoản giảm trừ.
             |
             | surcharge:
             | - Khoản phụ thu.
             |
             | other:
             | - Khoản khác chưa phân loại.
             |
             */
            $table->enum('charge_type', [
                    'room',
                    'electricity',
                    'water',
                    'garbage',
                    'internet',
                    'previous_debt',// nợ cũ
                    'damage_fee',
                    'discount',
                    'surcharge', // phụ thu
                    'deposit', //tiền thế chân
                    'other',
                ])
                ->comment('Loại khoản phí trong hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Mô tả hiển thị
             |--------------------------------------------------------------------------
             |
             | description là nội dung người dùng nhìn thấy trong chi tiết hóa đơn.
             |
             | Ví dụ:
             | - Tiền phòng tháng 06/2026
             | - Điện: 35 kWh x 3.500đ
             | - Nước: 8 m3, miễn 4 m3, tính tiền 4 m3
             |
             */
            $table->string('description', 255)
                ->comment('Mô tả dòng chi tiết hiển thị trên hóa đơn');

            /*
             |--------------------------------------------------------------------------
             | Đơn vị tính
             |--------------------------------------------------------------------------
             |
             | unit chỉ dùng để hiển thị và giải thích cách tính.
             |
             | Ví dụ:
             | - month
             | - kWh
             | - m3
             | - room
             | - time
             |
             */
            $table->string('unit', 50)
                ->nullable()
                ->comment('Đơn vị tính: month/kWh/m3/room/time..., dùng để hiển thị');

            /*
             |--------------------------------------------------------------------------
             | Số lượng tính tiền
             |--------------------------------------------------------------------------
             |
             | quantity:
             | - Với tiền phòng: thường là 1 tháng hoặc số ngày quy đổi.
             | - Với điện: số kWh tính tiền.
             | - Với nước: số m3 tính tiền sau khi trừ miễn phí nếu có.
             |
             */
            $table->decimal('quantity', 12, 2)
                ->default(1)
                ->comment('Số lượng tính tiền, ví dụ số tháng, số kWh, số m3');

            /*
             |--------------------------------------------------------------------------
             | Snapshot đơn giá
             |--------------------------------------------------------------------------
             |
             | unit_price_snapshot là đơn giá tại thời điểm lập hóa đơn.
             |
             | Không được phụ thuộc vào giá hiện tại trong service_prices,
             | vì sau này giá có thể thay đổi.
             |
             * Ví dụ:
             | - Điện hiện tại 3.500đ/kWh.
             | - Sau này đổi thành 4.000đ/kWh.
             | - Hóa đơn cũ vẫn phải giữ 3.500đ/kWh.
             |
             | Dùng bigInteger thay vì decimal vì tiền VND nên lưu số nguyên.
             |
             */
            $table->bigInteger('unit_price_snapshot')
                ->default(0)
                ->comment('Đơn giá tại thời điểm lập hóa đơn, lưu snapshot để không bị sai khi đổi giá');

            /*
             |--------------------------------------------------------------------------
             | Snapshot số lượng miễn phí
             |--------------------------------------------------------------------------
             |
             | free_quantity_snapshot dùng chủ yếu cho tiền nước.
             |
             | Ví dụ:
             | - Phòng có 2 người.
             | - Mỗi người được miễn 2 m3 nước.
             | - Tổng miễn phí = 4 m3.
             |
             | Khi lập hóa đơn phải snapshot lại số m3 miễn phí,
             | vì sau này nhân khẩu có thể thay đổi.
             |
             */
            $table->decimal('free_quantity_snapshot', 12, 2)
                ->default(0)
                ->comment('Số lượng miễn phí tại thời điểm lập hóa đơn, ví dụ số m3 nước được miễn');

            /*
             |--------------------------------------------------------------------------
             | Thành tiền
             |--------------------------------------------------------------------------
             |
             | amount là số tiền của dòng chi tiết.
             |
             | Công thức thường dùng:
             | amount = quantity * unit_price_snapshot
             |
             | Dùng bigInteger, không dùng unsignedBigInteger, vì một số dòng có thể âm:
             | - discount có thể là -50000
             | - điều chỉnh giảm có thể là số âm
             |
             */
            $table->bigInteger('amount')
                ->default(0)
                ->comment('Thành tiền của dòng chi tiết, có thể âm nếu là giảm trừ');

            /*
             |--------------------------------------------------------------------------
             | Thứ tự hiển thị
             |--------------------------------------------------------------------------
             |
             | sort_order giúp hiển thị dòng hóa đơn theo thứ tự mong muốn:
             | 1. Tiền phòng
             | 2. Điện
             | 3. Nước
             | 4. Rác
             | 5. Internet
             | 6. Phát sinh
             |
             */
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->comment('Thứ tự hiển thị dòng chi tiết trên hóa đơn');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Index hỗ trợ truy vấn
             |--------------------------------------------------------------------------
             |
             | invoice_id:
             | - Lấy danh sách dòng chi tiết của một hóa đơn.
             |
             | service_price_id:
             | - Truy vết dòng hóa đơn được tạo từ bảng giá nào.
             |
             | charge_type:
             | - Hỗ trợ báo cáo tổng tiền theo từng loại khoản phí.
             |
             */
            $table->index('invoice_id', 'idx_invoice_items_invoice_id');
            $table->index('service_price_id', 'idx_invoice_items_service_price_id');
            $table->index('charge_type', 'idx_invoice_items_charge_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};