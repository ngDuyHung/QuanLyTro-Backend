<?php

declare(strict_types=1);

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\RoomStatus;

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
            'floor_number' => ['sometimes', 'nullable', 'integer', 'min:-1', 'max:200'],
            'billing_day'   => ['sometimes', 'nullable', 'integer', 'min:0', 'max:31'],
            'allow_shared'  => ['sometimes', 'nullable', 'boolean'],
            'is_public'     => ['sometimes', 'nullable', 'boolean'],
            'status' => ['sometimes', new Enum(RoomStatus::class), 'in:available,maintenance'],
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
            'floor_number.integer' => 'Tầng phải là số nguyên.',
            'floor_number.min' => 'Tầng không hợp lệ.',
            'floor_number.max' => 'Tầng không được vượt quá 200.',
            'billing_day.integer' => 'Ngày thu tiền phải là số nguyên.',
            'billing_day.min' => 'Ngày thu tiền phải từ 1 đến 31.',
            'billing_day.max' => 'Ngày thu tiền phải từ 1 đến 31.',
            'allow_shared.boolean' => 'Giá trị cho phép ở ghép không hợp lệ.',
            'is_public.boolean' => 'Giá trị đăng công khai không hợp lệ.',
            'status.in' => 'Trạng thái phòng không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'          => 'tên phòng',
            'area'          => 'diện tích',
            'max_occupants' => 'số người tối đa',
            'current_price' => 'giá phòng',
            'floor_number' => 'tầng',
            'billing_day'   => 'ngày thu tiền',
            'allow_shared'  => 'cho phép ở ghép',
            'is_public'     => 'đăng công khai',
            'status'        => 'trạng thái',
            'description'   => 'mô tả',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('name')) {
            // Chuẩn hóa tên phòng: xóa khoảng trắng thừa và chuyển về null nếu rỗng
            $merge['name'] = $this->name ? trim((string) $this->name) : null;
        }

        if ($this->has('allow_shared')) {
            // Chuyển giá trị allow_shared về boolean (có thể nhận "true", "false", "1", "0", 1, 0)
            $merge['allow_shared'] = filter_var($this->allow_shared, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('is_public')) {
            // Chuyển giá trị is_public về boolean (có thể nhận "true", "false", "1", "0", 1, 0)
            $merge['is_public'] = filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}
