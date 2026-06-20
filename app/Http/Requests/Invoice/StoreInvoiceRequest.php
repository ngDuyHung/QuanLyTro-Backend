<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc tạo hóa đơn.
     *
     * Lưu ý:
     * - Không cho FE gửi invoice_code.
     * - Không cho FE gửi status.
     * - Không cho FE gửi total_amount trực tiếp.
     *
     * Các giá trị đó do backend tự sinh/tự tính trong InvoiceService.
     */
    public function rules(): array
    {
        return [
            /*
             |--------------------------------------------------------------------------
             | Thông tin hóa đơn chính
             |--------------------------------------------------------------------------
             */
            'lease_id' => [
                'required',
                'integer',
                'exists:leases,id',
            ],

            'invoice_type' => [
                'nullable',
                'in:monthly,checkin,checkout,adjustment',
            ],

            'period_from' => [
                'required',
                'date',
            ],

            'period_to' => [
                'required',
                'date',
                'after:period_from',
            ],

            'issue_date' => [
                'nullable',
                'date',
            ],

            'due_date' => [
                'nullable',
                'date',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            /*
             |--------------------------------------------------------------------------
             | Dòng chi tiết hóa đơn
             |--------------------------------------------------------------------------
             |
             | Mỗi hóa đơn bắt buộc có ít nhất 1 dòng.
             | amount là thành tiền của dòng chi tiết.
             |
             | Backend vẫn sẽ tự cộng lại tất cả dòng để ra total_amount.
             */
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.service_price_id' => [
                'nullable',
                'integer',
                'exists:service_prices,id',
            ],

            'items.*.charge_type' => [
                'required',
                'in:room,electricity,water,garbage,internet,previous_debt,damage_fee,discount,surcharge,other',
            ],

            'items.*.description' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.unit_price_snapshot' => [
                'required',
                'integer',
            ],

            'items.*.free_quantity_snapshot' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.amount' => [
                'required',
                'integer',
            ],

            'items.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            /*
             |--------------------------------------------------------------------------
             | Hóa đơn chính
             |--------------------------------------------------------------------------
             */
            'lease_id.required' => 'Vui lòng chọn hợp đồng thuê.',
            'lease_id.integer' => 'Hợp đồng thuê không hợp lệ.',
            'lease_id.exists' => 'Hợp đồng thuê không tồn tại.',

            'invoice_type.in' => 'Loại hóa đơn không hợp lệ.',

            'period_from.required' => 'Vui lòng nhập ngày bắt đầu kỳ hóa đơn.',
            'period_from.date' => 'Ngày bắt đầu kỳ hóa đơn không hợp lệ.',

            'period_to.required' => 'Vui lòng nhập ngày kết thúc kỳ hóa đơn.',
            'period_to.date' => 'Ngày kết thúc kỳ hóa đơn không hợp lệ.',
            'period_to.after' => 'Ngày kết thúc kỳ hóa đơn phải sau ngày bắt đầu.',

            'issue_date.date' => 'Ngày phát hành hóa đơn không hợp lệ.',
            'due_date.date' => 'Hạn thanh toán hóa đơn không hợp lệ.',

            /*
             |--------------------------------------------------------------------------
             | Dòng chi tiết
             |--------------------------------------------------------------------------
             */
            'items.required' => 'Vui lòng nhập chi tiết hóa đơn.',
            'items.array' => 'Chi tiết hóa đơn không hợp lệ.',
            'items.min' => 'Hóa đơn phải có ít nhất một dòng chi tiết.',

            'items.*.service_price_id.integer' => 'Mã bảng giá dịch vụ không hợp lệ.',
            'items.*.service_price_id.exists' => 'Bảng giá dịch vụ không tồn tại.',

            'items.*.charge_type.required' => 'Vui lòng chọn loại khoản phí.',
            'items.*.charge_type.in' => 'Loại khoản phí không hợp lệ.',

            'items.*.description.required' => 'Vui lòng nhập mô tả dòng hóa đơn.',
            'items.*.description.string' => 'Mô tả dòng hóa đơn phải là chuỗi ký tự.',
            'items.*.description.max' => 'Mô tả dòng hóa đơn không được vượt quá 255 ký tự.',

            'items.*.unit.string' => 'Đơn vị tính phải là chuỗi ký tự.',
            'items.*.unit.max' => 'Đơn vị tính không được vượt quá 50 ký tự.',

            'items.*.quantity.required' => 'Vui lòng nhập số lượng.',
            'items.*.quantity.numeric' => 'Số lượng phải là số.',
            'items.*.quantity.min' => 'Số lượng không được âm.',

            'items.*.unit_price_snapshot.required' => 'Vui lòng nhập đơn giá.',
            'items.*.unit_price_snapshot.integer' => 'Đơn giá phải là số nguyên.',

            'items.*.free_quantity_snapshot.numeric' => 'Số lượng miễn phí phải là số.',
            'items.*.free_quantity_snapshot.min' => 'Số lượng miễn phí không được âm.',

            'items.*.amount.required' => 'Vui lòng nhập thành tiền.',
            'items.*.amount.integer' => 'Thành tiền phải là số nguyên.',

            'items.*.sort_order.integer' => 'Thứ tự hiển thị phải là số nguyên.',
            'items.*.sort_order.min' => 'Thứ tự hiển thị không được âm.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lease_id' => 'hợp đồng thuê',
            'invoice_type' => 'loại hóa đơn',
            'period_from' => 'ngày bắt đầu kỳ hóa đơn',
            'period_to' => 'ngày kết thúc kỳ hóa đơn',
            'issue_date' => 'ngày phát hành',
            'due_date' => 'hạn thanh toán',
            'note' => 'ghi chú',

            'items' => 'chi tiết hóa đơn',
            'items.*.service_price_id' => 'bảng giá dịch vụ',
            'items.*.charge_type' => 'loại khoản phí',
            'items.*.description' => 'mô tả dòng hóa đơn',
            'items.*.unit' => 'đơn vị tính',
            'items.*.quantity' => 'số lượng',
            'items.*.unit_price_snapshot' => 'đơn giá',
            'items.*.free_quantity_snapshot' => 'số lượng miễn phí',
            'items.*.amount' => 'thành tiền',
            'items.*.sort_order' => 'thứ tự hiển thị',
        ];
    }
}