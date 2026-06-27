<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialTransaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'lease_id' => ['nullable', 'integer', 'exists:leases,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],

            'direction' => ['required', 'in:income,expense'],
            'category' => [
                'required',
                'in:holding_deposit,security_deposit,deposit_forfeit,refund_security_deposit,damage_fee,repair,operation,other_income,other_expense',
            ],
            'accounting_type' => [
                'required',
                'in:revenue,liability_in,liability_out,expense,receivable_adjustment',
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,bank_transfer,other'],
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
            'property_id.required' => 'Vui lòng chọn khu nhà.',
            'property_id.exists' => 'Khu nhà không tồn tại.',
            'direction.required' => 'Vui lòng chọn loại thu hoặc chi.',
            'direction.in' => 'Loại giao dịch không hợp lệ.',
            'category.required' => 'Vui lòng chọn loại nghiệp vụ thu chi.',
            'category.in' => 'Loại nghiệp vụ thu chi không hợp lệ.',
            'accounting_type.required' => 'Vui lòng chọn bản chất kế toán.',
            'accounting_type.in' => 'Bản chất kế toán không hợp lệ.',
            'amount.required' => 'Vui lòng nhập số tiền.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'method.required' => 'Vui lòng chọn phương thức thu chi.',
            'method.in' => 'Phương thức thu chi không hợp lệ.',
        ];
    }
}