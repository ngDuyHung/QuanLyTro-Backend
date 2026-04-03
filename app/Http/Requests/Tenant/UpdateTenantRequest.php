<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) $this->route('tenant');

        return [
            'full_name'           => ['sometimes', 'required', 'string', 'max:100'],
            'email'               => ['nullable', 'email', 'max:255', Rule::unique('tenants', 'email')->ignore($tenantId)],
            'phone'               => ['sometimes', 'required', 'string', 'regex:/^[0-9]{9,15}$/'],
            'id_card_number'      => ['sometimes', 'required', 'string', 'max:20', Rule::unique('tenants', 'id_card_number')->ignore($tenantId)],
            'id_card_front_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'id_card_back_image'  => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'           => 'Họ và tên không được để trống.',
            'full_name.max'                => 'Họ và tên không được vượt quá 100 ký tự.',
            'email.email'                  => 'Email không hợp lệ.',
            'email.unique'                 => 'Email này đã được sử dụng.',
            'phone.required'               => 'Số điện thoại không được để trống.',
            'phone.regex'                  => 'Số điện thoại không hợp lệ (9–15 chữ số).',
            'id_card_number.required'      => 'Số CCCD/CMND không được để trống.',
            'id_card_number.max'           => 'Số CCCD/CMND không được vượt quá 20 ký tự.',
            'id_card_number.unique'        => 'Số CCCD/CMND này đã tồn tại trong hệ thống.',
            'id_card_front_image.image'    => 'Ảnh mặt trước CCCD phải là file hình ảnh.',
            'id_card_front_image.mimes'    => 'Ảnh mặt trước CCCD chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'id_card_front_image.max'      => 'Ảnh mặt trước CCCD không được vượt quá 2MB.',
            'id_card_back_image.image'     => 'Ảnh mặt sau CCCD phải là file hình ảnh.',
            'id_card_back_image.mimes'     => 'Ảnh mặt sau CCCD chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'id_card_back_image.max'       => 'Ảnh mặt sau CCCD không được vượt quá 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name'           => 'họ và tên',
            'email'               => 'email',
            'phone'               => 'số điện thoại',
            'id_card_number'      => 'số CCCD/CMND',
            'id_card_front_image' => 'ảnh mặt trước CCCD',
            'id_card_back_image'  => 'ảnh mặt sau CCCD',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name'      => $this->full_name ? trim($this->full_name) : $this->full_name,
            'email'          => $this->email ? strtolower(trim($this->email)) : $this->email,
            'phone'          => $this->phone ? preg_replace('/\D/', '', $this->phone) : $this->phone,
            'id_card_number' => $this->id_card_number ? trim($this->id_card_number) : $this->id_card_number,
        ]);
    }
}
