<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'invoice_type' => ['nullable', 'in:monthly,checkin,checkout,adjustment'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after:period_from'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.service_price_id' => ['nullable', 'integer', 'exists:service_prices,id'],

            // Đã cập nhật rule chuẩn: dùng 'rent', 'management', v.v.
            'items.*.charge_type' => [
                'required',
                'string',
                Rule::in([
                    'room',
                    'electricity',
                    'water',
                    'garbage',
                    'internet',
                    'management',
                    'previous_debt',
                    'damage_fee',
                    'discount',
                    'surcharge',
                    'deposit',
                    'other'
                ]),
            ],

            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price_snapshot' => ['required', 'integer'],
            'items.*.free_quantity_snapshot' => ['nullable', 'numeric', 'min:0'],

            // Đã đặt nullable đúng chỗ, Backend service sẽ tự tính amount
            'items.*.amount' => ['nullable', 'integer'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lease_id.required' => 'Vui lòng chọn hợp đồng thuê.',
            'lease_id.integer' => 'Hợp đồng thuê không hợp lệ.',
            'lease_id.exists' => 'Hợp đồng thuê không tồn tại.',

            'period_from.required' => 'Vui lòng nhập ngày bắt đầu kỳ hóa đơn.',
            'period_to.required' => 'Vui lòng nhập ngày kết thúc kỳ hóa đơn.',
            'period_to.after' => 'Ngày kết thúc kỳ hóa đơn phải sau ngày bắt đầu.',

            'items.required' => 'Hóa đơn phải có ít nhất một dòng chi tiết.',
            'items.min' => 'Hóa đơn phải có ít nhất một dòng chi tiết.',

            // Chỉ trả về string thuần túy trong hàm này
            'items.*.charge_type.required' => 'Vui lòng chọn loại khoản phí.',
            'items.*.charge_type.in' => 'Loại khoản phí không hợp lệ.',
            'items.*.description.required' => 'Vui lòng nhập mô tả dòng hóa đơn.',
            'items.*.quantity.required' => 'Vui lòng nhập số lượng.',
            'items.*.unit_price_snapshot.required' => 'Vui lòng nhập đơn giá.',
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
