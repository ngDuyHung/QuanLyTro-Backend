<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialTransaction;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,bank_transfer'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'transaction_date' => ['nullable', 'date'],
            'transfer_content' => ['nullable', 'string', 'max:255'],
            'bank_transaction_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Vui lòng nhập số tiền thanh toán.',
            'amount.integer' => 'Số tiền thanh toán phải là số nguyên.',
            'amount.min' => 'Số tiền thanh toán phải lớn hơn 0.',
            'method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'method.in' => 'Phương thức thanh toán không hợp lệ.',
            'bank_account_id.exists' => 'Tài khoản ngân hàng không tồn tại.',
            'transaction_date.date' => 'Ngày thanh toán không hợp lệ.',
        ];
    }
}