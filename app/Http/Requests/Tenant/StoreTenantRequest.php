<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],

            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:15'],
            'id_card_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tenants', 'id_card_number'),
            ],

            'id_card_front_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'id_card_back_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'role' => ['nullable', Rule::in(['representative', 'member'])],
            'move_in_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => 'Vui lòng chọn phòng.',
            'room_id.exists' => 'Phòng không tồn tại.',

            'full_name.required' => 'Họ tên khách thuê không được để trống.',
            'full_name.max' => 'Họ tên không được vượt quá 100 ký tự.',

            'phone.required' => 'Số điện thoại không được để trống.',
            'phone.max' => 'Số điện thoại không được vượt quá 15 ký tự.',

            'id_card_number.required' => 'Số CCCD/CMND không được để trống.',
            'id_card_number.unique' => 'Số CCCD/CMND này đã tồn tại.',

            'email.email' => 'Email không đúng định dạng.',

            'id_card_front_image.image' => 'Ảnh mặt trước CCCD không hợp lệ.',
            'id_card_back_image.image' => 'Ảnh mặt sau CCCD không hợp lệ.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->full_name ? trim((string) $this->full_name) : null,
            'phone' => $this->phone ? trim((string) $this->phone) : null,
            'email' => $this->email ? trim((string) $this->email) : null,
            'id_card_number' => $this->id_card_number ? trim((string) $this->id_card_number) : null,

            'role' => $this->role ?: 'member',
            'move_in_date' => $this->move_in_date ?: now()->toDateString(),
        ]);
    }
}