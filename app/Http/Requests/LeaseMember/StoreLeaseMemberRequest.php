<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaseMember;

use App\Enums\MemberRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Cách 1: chọn khách thuê đã tồn tại trong hệ thống ─────────
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],

            // ── Cách 2: tạo khách thuê mới inline (khi không có tenant_id) ─
            'tenant'                       => ['required_without:tenant_id', 'nullable', 'array'],
            'tenant.full_name'             => ['required_with:tenant', 'string', 'max:100'],
            'tenant.email'                 => ['nullable', 'email', 'max:255', 'unique:tenants,email'],
            'tenant.phone'                 => ['required_with:tenant', 'string', 'regex:/^[0-9]{9,15}$/'],
            'tenant.id_card_number'        => ['required_with:tenant', 'string', 'max:20', 'unique:tenants,id_card_number'],
            'tenant.id_card_front_image'   => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'tenant.id_card_back_image'    => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // ── Thông tin quan hệ & ghi chú ───────────────────────────────
            'relationship' => ['required', Rule::enum(MemberRelationship::class)],
            'note'         => ['nullable', 'string', 'max:255'],
            'move_in_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists'                     => 'Khách thuê không tồn tại trong hệ thống.',
            'tenant.required_without'              => 'Vui lòng cung cấp thông tin khách thuê hoặc chọn khách thuê có sẵn.',
            'tenant.full_name.required_with'       => 'Họ và tên thành viên không được để trống.',
            'tenant.full_name.max'                 => 'Họ và tên không được vượt quá 100 ký tự.',
            'tenant.email.email'                   => 'Email không hợp lệ.',
            'tenant.email.unique'                  => 'Email này đã được sử dụng.',
            'tenant.phone.required_with'           => 'Số điện thoại thành viên không được để trống.',
            'tenant.phone.regex'                   => 'Số điện thoại không hợp lệ (9–15 chữ số).',
            'tenant.id_card_number.required_with'  => 'Số CCCD/CMND không được để trống.',
            'tenant.id_card_number.unique'         => 'Số CCCD/CMND này đã tồn tại trong hệ thống.',
            'tenant.id_card_front_image.image'     => 'Ảnh mặt trước CCCD phải là file hình ảnh.',
            'tenant.id_card_front_image.mimes'     => 'Ảnh mặt trước CCCD chỉ chấp nhận: jpg, jpeg, png, webp.',
            'tenant.id_card_front_image.max'       => 'Ảnh mặt trước CCCD không được vượt quá 2MB.',
            'tenant.id_card_back_image.image'      => 'Ảnh mặt sau CCCD phải là file hình ảnh.',
            'tenant.id_card_back_image.mimes'      => 'Ảnh mặt sau CCCD chỉ chấp nhận: jpg, jpeg, png, webp.',
            'tenant.id_card_back_image.max'        => 'Ảnh mặt sau CCCD không được vượt quá 2MB.',
            'relationship.required'                => 'Quan hệ với người đứng tên không được để trống.',
            'relationship.enum'                    => 'Quan hệ không hợp lệ. Chấp nhận: spouse, child, parent, sibling, friend, other.',
            'note.max'                             => 'Ghi chú không được vượt quá 255 ký tự.',
            'move_in_date.date'                    => 'Ngày chuyển vào không hợp lệ.',
            'move_in_date.date_format'             => 'Ngày chuyển vào phải đúng định dạng YYYY-MM-DD.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tenant_id'    => 'khách thuê',
            'relationship' => 'quan hệ',
            'note'         => 'ghi chú',
            'move_in_date' => 'ngày chuyển vào',
        ];
    }
}
