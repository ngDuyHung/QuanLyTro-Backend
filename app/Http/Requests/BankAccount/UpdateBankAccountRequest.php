<?php

declare(strict_types=1);

namespace App\Http\Requests\BankAccount;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_name'   => ['sometimes', 'required', 'string', 'max:100'],
            'account_number' => ['sometimes', 'required', 'string', 'max:30'],
            'bank_name'      => ['sometimes', 'required', 'string', 'max:100'],
            'bank_code'      => ['sometimes', 'required', 'string', 'max:20'],
            'branch'         => ['nullable', 'string', 'max:255'],
            'is_default'     => ['nullable', 'boolean'],
        ];
    }
}