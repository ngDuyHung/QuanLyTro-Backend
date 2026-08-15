<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Phân quyền sẽ được check ở Controller
    }

    public function rules(): array
    {

        $tenantData = $this->input('tenant', []);
        $phone = isset($tenantData['phone']) ? preg_replace('/\D/', '', (string) $tenantData['phone']) : null;
        $existingTenant = $phone
            ? \App\Models\Tenant::where('phone', $phone)
            ->where('owner_id', $this->user()->id) // THÊM ĐIỀU KIỆN NÀY
            ->first()
            : null;
        $tenantId = $existingTenant ? $existingTenant->id : null;
        return [
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
            'id_card_front_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_card_back_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'relationship' => ['required', Rule::in(['spouse', 'child', 'parent', 'sibling', 'friend', 'other'])],
            'move_in_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ và tên không được để trống.',
            'phone.unique' => 'Số điện thoại này đã được khách thuê khác sử dụng trong hệ thống.',
            'id_card_front_image.max' => 'Ảnh mặt trước không được vượt quá 5MB.',
            'id_card_back_image.max' => 'Ảnh mặt sau không được vượt quá 5MB.',
            'relationship.required' => 'Vui lòng chọn mối quan hệ.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->phone ? preg_replace('/\D/', '', (string) $this->phone) : null,
        ]);
    }
}
