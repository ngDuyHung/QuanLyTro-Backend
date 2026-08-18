<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\Property;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PreviewLedgerRequest extends FormRequest
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
            'include_deposit' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_type.required' => 'Vui lòng chọn loại kỳ chốt sổ.',
            'period_type.in' => 'Loại kỳ chốt sổ không hợp lệ.',
            'period_year.required' => 'Vui lòng chọn năm chốt sổ.',
            'period_month.required_if' => 'Vui lòng chọn tháng khi xem trước theo tháng.',
        ];
    }
}