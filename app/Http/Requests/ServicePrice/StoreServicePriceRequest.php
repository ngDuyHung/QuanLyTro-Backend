<?php

declare(strict_types=1);

namespace App\Http\Requests\ServicePrice;

use App\Enums\FreeUnitType;
use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreServicePriceRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'property_id' => [
                'nullable',
                'integer',
                // Vá bảo mật: Khu nhà bắt buộc phải thuộc về chủ trọ đang đăng nhập
                Rule::exists('properties', 'id')->where('user_id', $userId)
            ],
            'service_type' => [
                'required',
                new \Illuminate\Validation\Rules\Enum(\App\Enums\ServiceType::class),
                // Vá logic trùng lặp: Xử lý mệnh đề unique khôn ngoan cho cả trường hợp null và có ID
                Rule::unique('service_prices', 'service_type')->where(function ($query) {
                    if ($this->filled('property_id')) {
                        return $query->where('property_id', $this->input('property_id'));
                    }
                    return $query->whereNull('property_id');
                }),
            ],
            'unit_price'     => ['required', 'integer', 'min:0'],
            'base_price'     => ['nullable', 'integer', 'min:0'],
            'free_units'     => ['nullable', 'integer', 'min:0'],
            'free_unit_type' => ['nullable', new \Illuminate\Validation\Rules\Enum(\App\Enums\FreeUnitType::class)],
            'effective_date' => ['required', 'date'],
            'expiry_date'    => ['nullable', 'date', 'after_or_equal:effective_date'],
            'note'           => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.required' => 'Vui lòng chọn khu nhà.',
            'property_id.exists'   => 'Khu nhà không tồn tại.',
            'service_type.required' => 'Vui lòng chọn loại dịch vụ.',
            'service_type.in'      => 'Loại dịch vụ không hợp lệ.',
            'service_type.unique'  => 'Loại dịch vụ này đã có mức giá cho khu nhà được chọn.',
            'unit_price.required'  => 'Vui lòng nhập đơn giá.',
            'unit_price.integer'   => 'Đơn giá phải là số nguyên.',
            'unit_price.min'       => 'Đơn giá phải lớn hơn hoặc bằng 0.',
            'base_price.min'       => 'Phí cơ bản phải lớn hơn hoặc bằng 0.',
            'free_units.integer'   => 'Số đơn vị miễn phí phải là số nguyên.',
            'free_units.min'       => 'Số đơn vị miễn phí phải lớn hơn hoặc bằng 0.',
            'free_unit_type.in'    => 'Loại đơn vị miễn phí không hợp lệ.',
            'effective_date.required' => 'Vui lòng nhập ngày hiệu lực.',
            'effective_date.date'     => 'Ngày hiệu lực không hợp lệ.',
            'expiry_date.date'       => 'Ngày hết hạn không hợp lệ.',
            'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày hiệu lực.',
            'note.string'           => 'Ghi chú phải là chuỗi ký tự.',
            'note.max'              => 'Ghi chú không được vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'property_id'     => 'khu nhà',
            'service_type'   => 'loại dịch vụ',
            'unit_price'     => 'đơn giá',
            'base_price'    => 'phí cơ bản/Cố định',
            'free_units'     => 'số đơn vị miễn phí',
            'free_unit_type' => 'loại đơn vị miễn phí',
            'effective_date' => 'ngày hiệu lực',
            'expiry_date'    => 'ngày hết hạn',
            'note'           => 'ghi chú',
        ];
    }
}
