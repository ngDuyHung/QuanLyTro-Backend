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

        // TÌM XEM SĐT ĐÃ CÓ CHƯA
        $tenantData = $this->input('tenant', []);
        $phone = isset($tenantData['phone']) ? preg_replace('/\D/', '', (string) $tenantData['phone']) : null;
        $existingTenant = $phone
            ? \App\Models\Tenant::where('phone', $phone)
            ->where('owner_id', $this->user()->id) // THÊM ĐIỀU KIỆN NÀY
            ->first()
            : null;
        $tenantId = $existingTenant ? $existingTenant->id : null;
        return [
            // Phòng bắt buộc khi thêm khách từ danh mục khách thuê
            'room_id' => ['required', 'integer', 'exists:rooms,id'],

            // Thông tin khách thuê
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{9,15}$/',
                \Illuminate\Validation\Rule::unique('tenants', 'phone')->where('owner_id', $this->user()->id)->ignore($tenantId)
            ],
            'id_card_number' => [
                'nullable',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::unique('tenants', 'id_card_number')->where('owner_id', $this->user()->id)->ignore($tenantId)
            ],
            'id_card_front_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'id_card_back_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Thông tin ở ghép
            'relationship' => [
                'nullable',
                Rule::in(['spouse', 'child', 'parent', 'sibling', 'friend','roommate', 'other']),
            ],
            'move_in_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => 'Vui lòng chọn phòng.',
            'room_id.exists' => 'Phòng được chọn không tồn tại.',

            'full_name.required' => 'Họ và tên không được để trống.',
            'full_name.max' => 'Họ và tên không được vượt quá 100 ký tự.',

            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.required' => 'Số điện thoại không được để trống.',
            'phone.regex' => 'Số điện thoại không hợp lệ (9–15 chữ số).',
            'phone.unique' => 'Số điện thoại này đã được khách thuê khác sử dụng trong hệ thống.',
            'id_card_number.max' => 'Số CCCD/CMND không được vượt quá 20 ký tự.',
            'id_card_number.unique' => 'Số CCCD/CMND này đã tồn tại trong hệ thống.',

            'id_card_front_image.image' => 'Ảnh mặt trước CCCD phải là file hình ảnh.',
            'id_card_front_image.mimes' => 'Ảnh mặt trước CCCD chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'id_card_front_image.max' => 'Ảnh mặt trước CCCD không được vượt quá 2MB.',

            'id_card_back_image.image' => 'Ảnh mặt sau CCCD phải là file hình ảnh.',
            'id_card_back_image.mimes' => 'Ảnh mặt sau CCCD chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'id_card_back_image.max' => 'Ảnh mặt sau CCCD không được vượt quá 2MB.',

            'relationship.in' => 'Quan hệ với khách đại diện không hợp lệ.',
            'move_in_date.date' => 'Ngày chuyển vào không hợp lệ.',
            'note.max' => 'Ghi chú không được vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => 'phòng',
            'full_name' => 'họ và tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'id_card_number' => 'số CCCD/CMND',
            'id_card_front_image' => 'ảnh mặt trước CCCD',
            'id_card_back_image' => 'ảnh mặt sau CCCD',
            'relationship' => 'quan hệ',
            'move_in_date' => 'ngày chuyển vào',
            'note' => 'ghi chú',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->full_name ? trim((string) $this->full_name) : null,
            'email' => $this->email ? strtolower(trim((string) $this->email)) : null,
            'phone' => $this->phone ? preg_replace('/\D/', '', (string) $this->phone) : null,
            'id_card_number' => $this->id_card_number ? trim((string) $this->id_card_number) : null,

            'relationship' => $this->relationship ?: 'other',
            'move_in_date' => $this->move_in_date ?: now()->toDateString(),
        ]);
    }
}
