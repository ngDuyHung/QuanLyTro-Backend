<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class SaveLedgerTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'template.required' => 'Nội dung mẫu sổ kế toán không được để trống.',
            'template.string'   => 'Nội dung mẫu sổ kế toán không hợp lệ.',
        ];
    }
}