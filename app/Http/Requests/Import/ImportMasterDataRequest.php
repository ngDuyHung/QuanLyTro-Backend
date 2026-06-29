<?php

declare(strict_types=1);

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class ImportMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Khách thể đã qua middleware auth:sanctum ở Route
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240', // Giới hạn tối đa 10MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Excel hoặc CSV để import.',
            'file.file'     => 'Dữ liệu tải lên phải là một file hợp lệ.',
            'file.mimes'    => 'Hệ thống chỉ hỗ trợ định dạng file .xlsx, .xls hoặc .csv.',
            'file.max'      => 'Dung lượng file không được vượt quá 10MB.',
        ];
    }
}