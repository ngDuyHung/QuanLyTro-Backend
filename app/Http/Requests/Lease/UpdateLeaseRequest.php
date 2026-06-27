<?php

declare(strict_types=1);

namespace App\Http\Requests\Lease;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_day'          => ['sometimes', 'integer', 'min:1', 'max:28'],
            'deposit'              => ['sometimes', 'integer', 'min:0'],
            'move_out_notice_date' => ['nullable', 'date', 'date_format:Y-m-d'],

            'services'                      => ['nullable', 'array'],
            'services.*.service_type'       => ['required', 'string', new \Illuminate\Validation\Rules\Enum(\App\Enums\ServiceType::class)],
            'services.*.quantity'           => ['required', 'integer', 'min:1'],
            'services.*.custom_price'       => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'billing_day.integer'               => 'Ngày thu tiền phải là số nguyên.',
            'billing_day.min'                   => 'Ngày thu tiền tối thiểu là 1.',
            'billing_day.max'                   => 'Ngày thu tiền tối đa là 28.',
            'deposit.integer'                   => 'Tiền cọc phải là số nguyên.',
            'deposit.min'                       => 'Tiền cọc không được âm.',
            'move_out_notice_date.date'         => 'Ngày thông báo trả phòng không hợp lệ.',
            'move_out_notice_date.date_format'  => 'Ngày thông báo trả phòng phải đúng định dạng YYYY-MM-DD.',
            'services.*.service_type.string'    => 'Loại dịch vụ không hợp lệ.',
            'services.*.quantity.integer'       => 'Số lượng phải là số nguyên.',
            'services.*.quantity.min'           => 'Số lượng tối thiểu là 1.',
            'services.*.custom_price.integer'   => 'Giá tùy chỉnh phải là số nguyên.',
            'services.*.custom_price.min'       => 'Giá tùy chỉnh không được âm.',
        ];
    }

    public function attributes(): array
    {
        return [
            'billing_day'          => 'ngày thu tiền',
            'deposit'              => 'tiền cọc',
            'move_out_notice_date' => 'ngày thông báo trả phòng',
            'services.*.service_type' => 'loại dịch vụ',
            'services.*.quantity'     => 'số lượng',
            'services.*.custom_price' => 'giá tùy chỉnh',
        ];
    }
}
