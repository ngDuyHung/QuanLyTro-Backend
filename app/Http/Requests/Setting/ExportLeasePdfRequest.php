<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use App\Models\Lease;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ExportLeasePdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bốc tham số {id} từ URL đưa vào mảng dữ liệu để validate
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id') ? (int) $this->route('id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                // Sử dụng Custom Closure thay vì Rule::exists để có thể gọi Eloquent Relationships
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = Lease::where('id', $value)
                        ->whereHas('room.property', function ($query): void {
                            // Siết chặt quyền: Hợp đồng phải thuộc về Khu nhà của User đang đăng nhập
                            $query->where('user_id', $this->user()->id);
                        })
                        ->exists();

                    if (!$exists) {
                        $fail('Hợp đồng không tồn tại hoặc bạn không có quyền truy cập hợp đồng này.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Mã số hợp đồng không được để trống.',
            'id.integer'  => 'Mã số hợp đồng phải là số nguyên.',
        ];
    }
}