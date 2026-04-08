<?php

declare(strict_types=1);
namespace App\Http\Requests\ServicePrice;

use App\Enums\FreeUnitType;
use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
        return [
            'service_type'  => ['required', new Enum(ServiceType::class)],
            'unit_price'    => ['required', 'integer', 'min:0'],
            'free_units'    => ['nullable', 'integer', 'min:0'],
            'free_unit_type' => ['nullable', new Enum(FreeUnitType::class)],
            'effective_date' => ['required', 'date'],
            'expiry_date'    => ['nullable', 'date', 'after_or_equal:effective_date'],
            'note'           => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_type.required' => 'Loại dịch vụ không được để trống.',
            'service_type.enum' => 'Loại dịch vụ không hợp lệ.',
            'unit_price.min' => 'Giá dịch vụ phải lớn hơn hoặc bằng 0.',
            'free_units.min' => 'Số lượng miễn phí phải lớn hơn hoặc bằng 0.',
            'effective_date.required' => 'Ngày hiệu lực không được để trống.',
            'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày hiệu lực.',
            'note.max' => 'Ghi chú không được vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'service_type' => 'loại dịch vụ',
            'unit_price' => 'giá dịch vụ',
            'free_units' => 'số lượng miễn phí',
            'free_unit_type' => 'loại đơn vị miễn phí',
            'effective_date' => 'ngày hiệu lực',
            'expiry_date' => 'ngày hết hạn',
            'note' => 'ghi chú',
        ];
    }
}
