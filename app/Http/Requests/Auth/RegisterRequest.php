<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'phone'                 => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'Họ tên không được để trống.',
            'name.max'              => 'Họ tên không được vượt quá 255 ký tự.',
            'email.required'        => 'Email không được để trống.',
            'email.email'           => 'Email không đúng định dạng.',
            'email.unique'          => 'Email này đã được sử dụng.',
            'password.required'     => 'Mật khẩu không được để trống.',
            'password.min'          => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.confirmed'    => 'Xác nhận mật khẩu không khớp.',
            'phone.regex'           => 'Số điện thoại không hợp lệ (10-11 chữ số).',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'     => 'họ tên',
            'email'    => 'email',
            'password' => 'mật khẩu',
            'phone'    => 'số điện thoại',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email ?? '')),
            'phone' => $this->phone ? preg_replace('/\D/', '', $this->phone) : null,
        ]);
    }
}
