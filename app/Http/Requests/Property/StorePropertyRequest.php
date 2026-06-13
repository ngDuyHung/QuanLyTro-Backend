<?php

declare(strict_types=1);

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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

            'name' => ['required', 'string', 'max:150'],

            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('properties', 'code')
                    ->where(fn($query) => $query->where('user_id', $this->user()->id)),
            ],

            'status' => ['required', 'string', 'in:active,inactive'],

            'floors_count' => ['required', 'integer', 'min:0', 'max:200'],

            'expected_rooms_count' => ['required', 'integer', 'min:0', 'max:1000'],

            'manager_name' => ['nullable', 'string', 'max:100'],

            'address' => ['required', 'string', 'max:255'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],

            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'cover_image_path' => ['nullable', 'string', 'max:255'],

            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_type.required' => 'Loại khu nhà không được để trống.',
            'property_type.enum' => 'Loại khu nhà không hợp lệ.',

            'name.required' => 'Tên khu nhà không được để trống.',
            'name.max' => 'Tên khu nhà không vượt quá 150 ký tự.',

            'code.required' => 'Mã khu nhà không được để trống.',
            'code.max' => 'Mã khu nhà không vượt quá 50 ký tự.',
            'code.unique' => 'Mã khu nhà này đã tồn tại.',

            'status.required' => 'Trạng thái khu nhà không được để trống.',
            'status.in' => 'Trạng thái khu nhà không hợp lệ.',

            'floors_count.required' => 'Số tầng không được để trống.',
            'floors_count.integer' => 'Số tầng phải là số nguyên.',
            'floors_count.min' => 'Số tầng không được nhỏ hơn 0.',

            'expected_rooms_count.required' => 'Số phòng dự kiến không được để trống.',
            'expected_rooms_count.integer' => 'Số phòng dự kiến phải là số nguyên.',
            'expected_rooms_count.min' => 'Số phòng dự kiến không được nhỏ hơn 0.',

            'manager_name.max' => 'Tên người quản lý không vượt quá 100 ký tự.',

            'address.required' => 'Địa chỉ không được để trống.',
            'address.max' => 'Địa chỉ không vượt quá 255 ký tự.',

            'latitude.numeric' => 'Vĩ độ không hợp lệ.',
            'latitude.between' => 'Vĩ độ phải nằm trong khoảng -90 đến 90.',

            'longitude.numeric' => 'Kinh độ không hợp lệ.',
            'longitude.between' => 'Kinh độ phải nằm trong khoảng -180 đến 180.',

            'cover_image_path.max' => 'Đường dẫn ảnh không vượt quá 255 ký tự.',

            'description.max' => 'Mô tả không vượt quá 1000 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'property_type' => 'loại khu nhà',
            'name' => 'tên khu nhà',
            'code' => 'mã khu nhà',
            'status' => 'trạng thái',
            'floors_count' => 'số tầng',
            'expected_rooms_count' => 'số phòng dự kiến',
            'manager_name' => 'người quản lý',
            'address' => 'địa chỉ',
            'latitude' => 'vĩ độ',
            'longitude' => 'kinh độ',
            'cover_image_path' => 'ảnh đại diện',
            'description' => 'mô tả',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'property_type' => $this->property_type
                ? strtolower(trim((string) $this->property_type))
                : null,

            'name' => $this->name
                ? trim((string) $this->name)
                : null,

            'code' => $this->code
                ? strtoupper(trim((string) $this->code))
                : null,

            'status' => $this->status
                ? strtolower(trim((string) $this->status))
                : 'active',

            'address' => $this->address
                ? trim((string) $this->address)
                : null,

            'manager_name' => $this->manager_name
                ? trim((string) $this->manager_name)
                : null,

            'description' => $this->description
                ? trim((string) $this->description)
                : null,
        ]);
    }
}
