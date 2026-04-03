<?php

declare(strict_types=1);

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roomId     = $this->route('room');
        $propertyId = $this->route('propertyId');

        // Nếu không có propertyId trên route thì lấy từ DB (route /rooms/{id})
        if (!$propertyId) {
            $propertyId = \App\Models\Room::find($roomId)?->property_id;
        }

        return [
            'name'          => ['sometimes', 'string', 'max:50', "unique:rooms,name,{$roomId},id,property_id,{$propertyId}"],
            'area'          => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:9999.99'],
            'max_occupants' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'current_price' => ['sometimes', 'integer', 'min:0'],
            'description'   => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'               => 'Tên phòng không vượt quá 50 ký tự.',
            'name.unique'            => 'Tên phòng đã tồn tại trong khu nhà này.',
            'area.numeric'           => 'Diện tích phải là số.',
            'area.min'               => 'Diện tích phải lớn hơn 0.',
            'max_occupants.integer'  => 'Số người tối đa phải là số nguyên.',
            'max_occupants.min'      => 'Số người tối đa không được âm.',
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
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
