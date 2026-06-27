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

            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            'description' => ['nullable', 'string', 'max:1000'],

            'services'                  => ['nullable', 'array'],
            'services.*.service_type'   => ['required', 'string', new Enum(\App\Enums\ServiceType::class)],
            'services.*.unit_price'     => ['required', 'integer', 'min:0'],
            'services.*.free_units'     => ['nullable', 'integer', 'min:0'],
            'services.*.free_unit_type' => ['nullable', new Enum(\App\Enums\FreeUnitType::class)],
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

            'cover_image.image' => 'Ảnh đại diện khu nhà không hợp lệ.',
            'cover_image.mimes' => 'Ảnh đại diện chỉ hỗ trợ định dạng jpg, jpeg, png hoặc webp.',
            'cover_image.max' => 'Ảnh đại diện không được vượt quá 4MB.',

            'description.max' => 'Mô tả không vượt quá 1000 ký tự.',

            'services.*.service_type.required' => 'Loại dịch vụ không được để trống.',
            'services.*.service_type.enum' => 'Loại dịch vụ không hợp lệ.',
            'services.*.unit_price.required' => 'Đơn giá dịch vụ không được để trống.',
            'services.*.unit_price.integer' => 'Đơn giá dịch vụ phải là số nguyên.',
            'services.*.unit_price.min' => 'Đơn giá dịch vụ không được nhỏ hơn 0.',
            'services.*.free_units.integer' => 'Số lượng miễn phí phải là số nguyên.',
            'services.*.free_units.min' => 'Số lượng miễn phí không được nhỏ hơn 0.',
            'services.*.free_unit_type.enum' => 'Loại đơn vị miễn phí không hợp lệ.',
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
            'cover_image' => 'ảnh đại diện',
            'description' => 'mô tả',
            'services.*.service_type' => 'loại dịch vụ',
            'services.*.unit_price' => 'đơn giá dịch vụ',
            'services.*.free_units' => 'số lượng miễn phí',
            'services.*.free_unit_type' => 'loại đơn vị miễn phí',
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
