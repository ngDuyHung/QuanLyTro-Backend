<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'invoice_code' => ['required', 'string', 'max:255'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after:period_from'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', new Enum(InvoiceStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'lease_id.required' => 'Vui lòng chọn hợp đồng thuê.',
            'lease_id.integer' => 'Hợp đồng thuê phải là số nguyên.',
            'lease_id.exists' => 'Hợp đồng thuê không tồn tại.',
            'invoice_code.required' => 'Vui lòng nhập mã hóa đơn.',
            'invoice_code.string' => 'Mã hóa đơn phải là chuỗi ký tự.',
            'invoice_code.max' => 'Mã hóa đơn không được vượt quá 255 ký tự.',
            'period_from.required' => 'Vui lòng nhập ngày bắt đầu kỳ hóa đơn.',
            'period_from.date' => 'Ngày bắt đầu kỳ hóa đơn không hợp lệ.',
            'period_to.required' => 'Vui lòng nhập ngày kết thúc kỳ hóa đơn.',
            'period_to.date' => 'Ngày kết thúc kỳ hóa đơn không hợp lệ.',
            'period_to.after' => 'Ngày kết thúc kỳ hóa đơn phải sau ngày bắt đầu.',
            'total_amount.required' => 'Vui lòng nhập tổng số tiền.',
            'total_amount.numeric' => 'Tổng số tiền phải là số.',
            'total_amount.min' => 'Tổng số tiền không được âm.',
            'status.required' => 'Vui lòng chọn trạng thái hóa đơn.',
            'status.enum' => 'Trạng thái hóa đơn không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lease_id' => 'hợp đồng thuê',
            'invoice_code' => 'mã hóa đơn',
            'period_from' => 'ngày bắt đầu kỳ hóa đơn',
            'period_to' => 'ngày kết thúc kỳ hóa đơn',
            'total_amount' => 'tổng số tiền',
            'status' => 'trạng thái hóa đơn',
        ];
    }
}
