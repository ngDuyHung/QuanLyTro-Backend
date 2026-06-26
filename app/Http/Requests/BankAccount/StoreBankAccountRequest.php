<?php

declare(strict_types=1);

namespace App\Http\Requests\BankAccount;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_name'   => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:30', 'unique:bank_accounts,account_number'],
            'bank_name'      => ['required', 'string', 'max:100'],
            'bank_code'      => ['required', 'string', 'max:20'],
            'branch'         => ['nullable', 'string', 'max:255'],
            'is_default'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_name.required'   => 'Vui lòng nhập tên chủ tài khoản.',
            'account_name.max'        => 'Tên chủ tài khoản không được vượt quá 100 ký tự.',
            'account_number.max'      => 'Số tài khoản không được vượt quá 30 ký tự.',
            'account_number.unique'   => 'Số tài khoản đã tồn tại trong hệ thống.',
            'bank_name.max'           => 'Tên ngân hàng không được vượt quá 100 ký tự.',
            'bank_code.max'           => 'Mã ngân hàng không được vượt quá 20 ký tự.',
            'branch.max'              => 'Chi nhánh ngân hàng không được vượt quá 255 ký tự.',
            'is_default.boolean'      => 'Giá trị mặc định phải là true hoặc false.',
            'account_number.required' => 'Vui lòng nhập số tài khoản.',
            'bank_name.required'      => 'Vui lòng nhập tên ngân hàng.',
            'bank_code.required'      => 'Vui lòng nhập mã ngân hàng (VD: MB, VCB).',
        ];
    }

    public function attributes(): array
    {
        return [
            'account_name'   => 'tên chủ tài khoản',
            'account_number' => 'số tài khoản',
            'bank_name'      => 'tên ngân hàng',
            'bank_code'      => 'mã ngân hàng',
            'branch'         => 'chi nhánh ngân hàng',
            'is_default'     => 'mặc định',
        ];
    }
}