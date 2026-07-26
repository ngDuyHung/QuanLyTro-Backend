<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Lấy ID của tenant đang được sửa từ Route: api/v1/tenant/members/{member}
        $tenantId = (int) $this->route('member');

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{9,15}$/', Rule::unique('tenants', 'phone')->ignore($tenantId)],
            'id_card_number' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('tenants', 'id_card_number')->ignore($tenantId)],
            
            'id_card_front_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_card_back_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'relationship' => ['sometimes', 'required', Rule::in(['spouse', 'child', 'parent', 'sibling', 'friend', 'other'])],
            'move_in_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => preg_replace('/\D/', '', (string) $this->phone)]);
        }
    }
}