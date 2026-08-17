<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone'    => ['required', 'string', 'regex:/^0[0-9]{8,14}$/'],
            'password' => ['required', 'string'],
            'zalo_link_token' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required'    => 'Số điện thoại không được để trống.',
            'phone.regex'       => 'Số điện thoại phải bắt đầu bằng số 0 và có từ 9 đến 15 chữ số.',
            'password.required' => 'Mật khẩu không được để trống.',
            'zalo_link_token.string' => 'Phiên liên kết Zalo không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone'    => 'số điện thoại',
            'password' => 'mật khẩu',
            'zalo_link_token' => 'phiên liên kết Zalo',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Loại bỏ khoảng trắng, dấu chấm, dấu gạch ngang khỏi số điện thoại trước khi xác thực
        $phone = trim((string) $this->input('phone', ''));

        $this->merge([
            'phone' => preg_replace('/[\s\.\-]/', '', $phone),
        ]);
    }
}
