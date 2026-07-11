<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use App\Enums\Incident\IncidentPayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ResolveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repair_cost' => ['required', 'integer', 'min:0'],
            'payer' => ['required', new Enum(IncidentPayer::class)],
            
            // Tùy chọn: Upload ảnh sau khi đã sửa xong (after_repair)
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'repair_cost.required' => 'Vui lòng nhập chi phí sửa chữa (nhập 0 nếu không tốn phí).',
            'repair_cost.integer' => 'Chi phí sửa chữa phải là số nguyên.',
            'repair_cost.min' => 'Chi phí sửa chữa không được âm.',
            'payer.required' => 'Vui lòng chọn bên chịu chi phí sửa chữa.',
        ];
    }
}