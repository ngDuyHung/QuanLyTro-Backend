<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\Property;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class LockLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value) {
                        $exists = Property::where('id', $value)
                            ->where('user_id', $this->user()->id)
                            ->exists();

                        if (!$exists) {
                            $fail('Khu nhà không tồn tại hoặc bạn không có quyền truy cập.');
                        }
                    }
                },
            ],
            
            'period_type'  => ['required', 'string', 'in:month,quarter,year'],
            'period_year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required_if:period_type,month', 'nullable', 'integer', 'min:1', 'max:12'],
            'note'         => ['nullable', 'string', 'max:1000'],

            // ===== THÊM MỚI: VALIDATE MẢNG CHI TIẾT SỔ TỪ FRONTEND =====
            'details'                      => ['required', 'array', 'min:1'], // Bắt buộc phải có ít nhất 1 dòng
            'details.*.transaction_date'   => ['nullable', 'date'],
            'details.*.transaction_code'   => ['nullable', 'string', 'max:50'],
            'details.*.description'        => ['nullable', 'string', 'max:1000'],
            'details.*.amount'             => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_type.required'         => 'Vui lòng chọn loại kỳ chốt sổ.',
            'period_type.in'               => 'Loại kỳ chốt sổ không hợp lệ.',
            'period_year.required'         => 'Vui lòng chọn năm chốt sổ.',
            'period_month.required_if'     => 'Vui lòng chọn tháng khi chốt sổ theo tháng.',
            'details.required'             => 'Danh sách chi tiết sổ không được để trống.',
            'details.min'                  => 'Sổ kế toán phải có ít nhất 1 dòng giao dịch.',
            'details.*.amount.required'    => 'Có dòng giao dịch bị thiếu số tiền.',
            'details.*.amount.min'         => 'Số tiền giao dịch không được số âm.',
        ];
    }
}