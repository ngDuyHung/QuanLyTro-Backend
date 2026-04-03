<?php

declare(strict_types=1);

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $propertyId = $this->route('propertyId');

        return [
            'name'          => ['required', 'string', 'max:50', "unique:rooms,name,NULL,id,property_id,{$propertyId}"],
            'area'          => ['nullable', 'numeric', 'min:1', 'max:9999.99'],
            'max_occupants' => ['nullable', 'integer', 'min:0', 'max:255'],
            'current_price' => ['required', 'integer', 'min:0'],
            'description'   => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Tên phòng không được để trống.',
            'name.max'               => 'Tên phòng không vượt quá 50 ký tự.',
            'name.unique'            => 'Tên phòng đã tồn tại trong khu nhà này.',
            'area.numeric'           => 'Diện tích phải là số.',
            'area.min'               => 'Diện tích phải lớn hơn 0.',
            'max_occupants.integer'  => 'Số người tối đa phải là số nguyên.',
            'max_occupants.min'      => 'Số người tối đa không được âm.',
            'current_price.required' => 'Giá phòng không được để trống.',
            'current_price.integer'  => 'Giá phòng phải là số nguyên.',
            'current_price.min'      => 'Giá phòng không được âm.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'          => 'tên phòng',
            'area'          => 'diện tích',
            'max_occupants' => 'số người tối đa',
            'current_price' => 'giá phòng',
            'description'   => 'mô tả',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->name ? trim($this->name) : null,
        ]);
    }
}
