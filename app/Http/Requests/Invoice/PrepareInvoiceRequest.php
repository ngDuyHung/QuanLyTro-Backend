<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class PrepareInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'period_to' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'lease_id.required' => 'Vui lòng chọn hợp đồng (phòng) để tạo hóa đơn.',
            'period_to.required' => 'Vui lòng chọn ngày kết thúc kỳ để chốt chỉ số.',
        ];
    }
}