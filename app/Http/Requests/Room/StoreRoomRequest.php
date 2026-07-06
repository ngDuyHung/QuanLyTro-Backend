<?php

declare(strict_types=1);

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\RoomStatus;
use Illuminate\Validation\Rules\Enum;

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
            'deposit_amount' => ['nullable', 'integer', 'min:0'],
            'floor_number' => ['nullable', 'integer', 'min:-1', 'max:200'],
            'billing_day'   => ['nullable', 'integer', 'min:1', 'max:31'],
            'allow_shared'  => ['nullable', 'boolean'],
            'is_public'     => ['nullable', 'boolean'],
            'status' => ['required', new Enum(RoomStatus::class), 'in:available,maintenance'],
            'description'   => ['nullable', 'string'],

            'images' => ['nullable', 'array', 'max:5'],

            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',],
            'cover_image_index' => ['nullable', 'integer', 'min:0', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên phòng không được để trống.',
            'name.max' => 'Tên phòng không vượt quá 50 ký tự.',
            'name.unique' => 'Tên phòng đã tồn tại trong khu nhà này.',

            'floor_number.integer' => 'Tầng phải là số nguyên.',
            'floor_number.min' => 'Tầng không hợp lệ.',
            'floor_number.max' => 'Tầng không được vượt quá 200.',

            'area.numeric' => 'Diện tích phải là số.',
            'area.min' => 'Diện tích phải lớn hơn 0.',

            'max_occupants.integer' => 'Số người tối đa phải là số nguyên.',
            'max_occupants.min' => 'Số người tối đa không được âm.',

            'current_price.required' => 'Giá phòng không được để trống.',
            'current_price.integer' => 'Giá phòng phải là số nguyên.',
            'current_price.min' => 'Giá phòng không được âm.',

            'deposit_amount.required' => 'Số tiền cọc/thế chân không được để trống.',
            'deposit_amount.integer' => 'Số tiền cọc/thế chân phải là số nguyên.',
            'deposit_amount.min' => 'Số tiền cọc/thế chân không được âm.',

            'billing_day.integer' => 'Ngày thu tiền phải là số nguyên.',
            'billing_day.min' => 'Ngày thu tiền phải từ 1 đến 31.',
            'billing_day.max' => 'Ngày thu tiền phải từ 1 đến 31.',

            'allow_shared.boolean' => 'Giá trị cho phép ở ghép không hợp lệ.',
            'is_public.boolean' => 'Giá trị đăng công khai không hợp lệ.',

            'status.in' => 'Trạng thái phòng không hợp lệ.',
            'status.enum' => 'Trạng thái phòng không hợp lệ.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'images.array' => 'Ảnh phải là một mảng.',
            'images.max' => 'Không được tải lên quá 5 ảnh.',
            'images.*.file' => 'Mỗi ảnh phải là một file.',
            'images.*.image' => 'Mỗi file phải là một ảnh.',
            'images.*.mimes' => 'Mỗi ảnh phải có định dạng jpg, jpeg, png hoặc webp.',
            'images.*.max' => 'Mỗi ảnh không được vượt quá 5MB.',
            'cover_image_index.integer' => 'Chỉ số ảnh bìa phải là số nguyên.',
            'cover_image_index.min' => 'Chỉ số ảnh bìa không hợp lệ.',
            'cover_image_index.max' => 'Chỉ số ảnh bìa không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'          => 'tên phòng',
            'area'          => 'diện tích',
            'max_occupants' => 'số người tối đa',
            'current_price' => 'giá phòng',
            'deposit_amount' => 'số tiền cọc/thế chân',
            'floor_number' => 'tầng',
            'billing_day'   => 'ngày thu tiền',
            'allow_shared'  => 'cho phép ở ghép',
            'is_public'     => 'đăng công khai',
            'status'        => 'trạng thái',
            'description'   => 'mô tả',
            'images'        => 'ảnh',
            'cover_image_index' => 'chỉ số ảnh bìa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->name ? trim((string) $this->name) : null,

            'status' => $this->status ?: 'available',

            'max_occupants' => $this->filled('max_occupants')
                ? (int) $this->max_occupants
                : 0,

            'allow_shared' => $this->has('allow_shared')
                ? filter_var($this->allow_shared, FILTER_VALIDATE_BOOLEAN)
                : false,

            'is_public' => $this->has('is_public')
                ? filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN)
                : false,
        ]);
    }
}
