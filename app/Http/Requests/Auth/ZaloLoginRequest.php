<?php
declare(strict_types=1);
namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ZaloLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'code_verifier' => ['required', 'string'],
        ];
    }


    public function messages(): array
    {
        return [
            'code.required'          => 'Mã xác thực Zalo không được để trống.',
            'code.string'            => 'Mã xác thực Zalo không hợp lệ.',
            'code_verifier.required' => 'Mã xác minh Zalo không được để trống.',
            'code_verifier.string'   => 'Mã xác minh Zalo không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code'          => 'mã xác thực Zalo',
            'code_verifier' => 'mã xác minh Zalo',
        ];
    }
}
