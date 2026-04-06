<?php

declare(strict_types=1);
namespace App\Http\Requests\ServicePrice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServicePriceRequest extends FormRequest
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
        $servicePriceId = (int) $this->route('service_price');
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên dịch vụ không được để trống.',
            'name.max' => 'Tên dịch vụ không được vượt quá 100 ký tự.',
            'price.required' => 'Giá dịch vụ không được để trống.',
            'price.numeric' => 'Giá dịch vụ phải là một số.',
            'price.min' => 'Giá dịch vụ phải lớn hơn hoặc bằng 0.',
            'unit.required' => 'Đơn vị tính không được để trống.',
            'unit.max' => 'Đơn vị tính không được vượt quá 50 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên dịch vụ',
            'price' => 'giá dịch vụ',
            'unit' => 'đơn vị tính',
        ];
    }
}
