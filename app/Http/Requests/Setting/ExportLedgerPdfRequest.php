<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use App\Models\AccountingLedger;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ExportLedgerPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id') ? (int) $this->route('id') : null,
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

    public function messages(): array
    {
        return [
            'id.required' => 'Mã sổ kế toán không được để trống.',
            'id.integer'  => 'Mã sổ kế toán không hợp lệ.',
        ];
    }
}
