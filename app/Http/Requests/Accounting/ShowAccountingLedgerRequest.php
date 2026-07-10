<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\AccountingLedger;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ShowAccountingLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Lấy ID từ URL (route parameter có thể là 'id' hoặc 'accounting_ledger')
        $this->merge([
            'id' => $this->route('accounting_ledger') ?? $this->route('id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = AccountingLedger::query()
                        ->where('id', $value)
                        ->where('user_id', $this->user()->id)
                        ->exists();

                    if (!$exists) {
                        $fail('Sổ kế toán không tồn tại hoặc bạn không có quyền truy cập.');
                    }
                },
            ],
        ];
    }
}