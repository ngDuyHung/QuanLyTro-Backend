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
            'phone'                 => ['required', 'string', 'unique:users,phone', 'regex:/^0[0-9]{9,10}$/'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Họ tên không được để trống.',
            'name.string'        => 'Họ tên không hợp lệ.',
            'name.max'           => 'Họ tên không được vượt quá 255 ký tự.',

            'phone.required'     => 'Số điện thoại không được để trống.',
            'phone.string'       => 'Số điện thoại không hợp lệ.',
            'phone.unique'       => 'Số điện thoại này đã được sử dụng.',
            'phone.regex'        => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng số 0.',

            'password.required'  => 'Mật khẩu không được để trống.',
            'password.string'    => 'Mật khẩu không hợp lệ.',
            'password.min'       => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'     => 'họ tên',
            'phone'    => 'số điện thoại',
            'password' => 'mật khẩu',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Loại bỏ khoảng trắng ở đầu và cuối họ tên trước khi xác thực
            'name'  => trim((string) $this->input('name', '')),
            // Loại bỏ khoảng trắng, dấu chấm, dấu gạch ngang khỏi số điện thoại trước khi xác thực
            'phone' => $this->input('phone')
                ? preg_replace('/\D/', '', (string) $this->input('phone'))
                : null,
        ]);
    }
}
