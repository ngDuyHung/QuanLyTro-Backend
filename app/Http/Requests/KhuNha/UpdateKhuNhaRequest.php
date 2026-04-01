<?php

declare(strict_types=1);

namespace App\Http\Requests\KhuNha;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKhuNhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ten_khu' => ['sometimes', 'required', 'string', 'max:100'],
            'dia_chi' => ['sometimes', 'required', 'string', 'max:255'],
            'mo_ta'   => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ten_khu.required' => 'Tên khu nhà không được để trống.',
            'ten_khu.max'      => 'Tên khu nhà không vượt quá 100 ký tự.',
            'dia_chi.required' => 'Địa chỉ không được để trống.',
            'dia_chi.max'      => 'Địa chỉ không vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ten_khu' => 'tên khu nhà',
            'dia_chi' => 'địa chỉ',
            'mo_ta'   => 'mô tả',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('ten_khu')) {
            $data['ten_khu'] = trim($this->ten_khu ?? '');
        }

        if ($this->has('dia_chi')) {
            $data['dia_chi'] = trim($this->dia_chi ?? '');
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }
}
