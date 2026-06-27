<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialTransaction;

use Illuminate\Foundation\Http\FormRequest;

class CancelFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy giao dịch.',
            'cancel_reason.max' => 'Lý do hủy không được vượt quá 255 ký tự.',
        ];
    }
}