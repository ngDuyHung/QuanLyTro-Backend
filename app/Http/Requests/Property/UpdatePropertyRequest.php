<?php

declare(strict_types=1);

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'address'     => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Tên khu nhà không được để trống.',
            'name.max'         => 'Tên khu nhà không vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ không được để trống.',
            'address.max'      => 'Địa chỉ không vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'        => 'tên khu nhà',
            'address'     => 'địa chỉ',
            'description' => 'mô tả',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name')) {
            $data['name'] = trim($this->name ?? '');
        }

        if ($this->has('address')) {
            $data['address'] = trim($this->address ?? '');
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }
}
