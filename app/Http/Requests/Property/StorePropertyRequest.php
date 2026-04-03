<?php

declare(strict_types=1);

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_type' => ['required', new Enum(PropertyType::class)],
            'name'          => ['required', 'string', 'max:100'],
            'address'       => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_type.required' => 'Loại khu nhà không được để trống.',
            'property_type.enum'     => 'Loại khu nhà không hợp lệ.',
            'name.required'    => 'Tên khu nhà không được để trống.',
            'name.max'         => 'Tên khu nhà không vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ không được để trống.',
            'address.max'      => 'Địa chỉ không vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'property_type' => 'loại khu nhà',
            'name'        => 'tên khu nhà',
            'address'     => 'địa chỉ',
            'description' => 'mô tả',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'    => $this->name ? trim($this->name) : null,
            'address' => $this->address ? trim($this->address) : null,
            'property_type' => $this->property_type ? strtolower($this->property_type) : null,
        ]);
    }
}
