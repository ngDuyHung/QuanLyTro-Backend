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
        // 1. Tìm ra chủ trọ (owner_id) thông qua Hợp đồng đang chọn
        $userId = $this->user()->id;
        $leaseIdHeader = $this->header('X-Lease-Id');

        $query = \App\Models\Lease::with('room.property')
            ->where('status', 'active')
            ->where(function ($q) use ($userId) {
                $q->whereHas('tenant', fn($t) => $t->where('user_id', $userId))
                    ->orWhereHas('members.tenant', fn($t) => $t->where('user_id', $userId));
            });

        if ($leaseIdHeader) {
            $query->where('id', $leaseIdHeader);
        }

        $lease = $query->first();
        // Lấy ID chủ trọ, nếu không tìm thấy gán = 0 để query không bị lỗi
        $ownerId = $lease ? $lease->room->property->user_id : 0;

        // 2. Kiểm tra sđt
        $phoneRaw = $this->input('phone');
        $phone = $phoneRaw ? preg_replace('/\D/', '', (string) $phoneRaw) : null;

        $existingTenant = $phone
            ? \App\Models\Tenant::where('phone', $phone)
            ->where('owner_id', $ownerId) // Sửa thành $ownerId
            ->first()
            : null;

        $tenantId = $existingTenant ? $existingTenant->id : null;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{9,15}$/',
                \Illuminate\Validation\Rule::unique('tenants', 'phone')->where('owner_id', $ownerId)->ignore($tenantId)
            ],
            'id_card_number' => [
                'nullable',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::unique('tenants', 'id_card_number')->where('owner_id', $ownerId)->ignore($tenantId)
            ],
            'id_card_front_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_card_back_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'relationship' => ['sometimes', 'required', Rule::in(['spouse', 'child', 'parent', 'sibling', 'friend','roommate', 'other'])],
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
