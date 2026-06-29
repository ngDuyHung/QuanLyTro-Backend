<?php

declare(strict_types=1);

namespace App\Http\Requests\Lease;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Thông tin hợp đồng ──────────────────────────────────────
            'room_id'             => ['required', 'integer', 'exists:rooms,id'],
            'start_date'          => ['required', 'date', 'date_format:Y-m-d'],
            'billing_day'         => ['nullable', 'integer', 'min:1', 'max:28'],
            'deposit'             => ['nullable', 'integer', 'min:0'],
            'room_price'          => ['nullable', 'integer', 'min:0'],
            'occupants_count' => ['nullable', 'integer', 'min:1'],
            'electricity_reading' => ['required', 'integer', 'min:0'],
            'water_reading'       => ['required', 'integer', 'min:0'],
            'electricity_image'   => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'water_image'         => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // ── Thông tin khách thuê (tạo mới cùng lúc) ─────────────────
            'tenant'                       => ['required', 'array'],
            'tenant.full_name'             => ['required', 'string', 'max:100'],
            'tenant.email'                 => ['nullable', 'email', 'max:255', 'unique:tenants,email'],
            'tenant.phone'                 => ['required', 'string', 'regex:/^[0-9]{9,15}$/'],
            'tenant.id_card_number'        => ['required', 'string', 'max:20', 'unique:tenants,id_card_number'],
            'tenant.id_card_front_image'   => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'tenant.id_card_back_image'    => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'services'                      => ['nullable', 'array'],
            'services.*.service_type'       => ['required', 'string', new \Illuminate\Validation\Rules\Enum(\App\Enums\ServiceType::class)],
            'services.*.quantity'           => ['required', 'integer', 'min:1'],
            'services.*.custom_price'       => ['nullable', 'integer', 'min:0'],

        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required'                     => 'Vui lòng chọn phòng.',
            'room_id.exists'                       => 'Phòng không tồn tại.',
            'start_date.required'                  => 'Ngày bắt đầu hợp đồng không được để trống.',
            'start_date.date'                      => 'Ngày bắt đầu không hợp lệ.',
            'start_date.date_format'               => 'Ngày bắt đầu phải đúng định dạng YYYY-MM-DD.',
            'billing_day.integer'                  => 'Ngày thu tiền phải là số nguyên.',
            'billing_day.min'                      => 'Ngày thu tiền tối thiểu là 1.',
            'billing_day.max'                      => 'Ngày thu tiền tối đa là 28.',
            'deposit.integer'                      => 'Tiền cọc phải là số nguyên.',
            'deposit.min'                          => 'Tiền cọc không được âm.',
            'room_price.integer'                   => 'Giá thuê phòng phải là số nguyên.',
            'room_price.min'                       => 'Giá thuê phòng không được âm.',
            'occupants_count.integer'              => 'Số lượng người ở phải là số nguyên.',
            'occupants_count.min'                  => 'Số lượng người ở tối thiểu là 1.',
            'electricity_reading.required'         => 'Chỉ số điện ban đầu không được để trống.',
            'electricity_reading.integer'          => 'Chỉ số điện phải là số nguyên.',
            'electricity_reading.min'              => 'Chỉ số điện không được âm.',
            'water_reading.required'               => 'Chỉ số nước ban đầu không được để trống.',
            'water_reading.integer'                => 'Chỉ số nước phải là số nguyên.',
            'water_reading.min'                    => 'Chỉ số nước không được âm.',

            'electricity_image.image'              => 'Ảnh đồng hồ điện phải là định dạng hình ảnh.',
            'electricity_image.mimes'              => 'Ảnh đồng hồ điện chỉ chấp nhận: jpg, jpeg, png, webp.',
            'electricity_image.max'                => 'Ảnh đồng hồ điện không được vượt quá 4MB.',
            'water_image.image'                    => 'Ảnh đồng hồ nước phải là định dạng hình ảnh.',
            'water_image.mimes'                    => 'Ảnh đồng hồ nước chỉ chấp nhận: jpg, jpeg, png, webp.',
            'water_image.max'                      => 'Ảnh đồng hồ nước không được vượt quá 4MB.',


            'tenant.required'                      => 'Thông tin khách thuê không được để trống.',
            'tenant.full_name.required'            => 'Họ và tên khách thuê không được để trống.',
            'tenant.full_name.max'                 => 'Họ và tên không được vượt quá 100 ký tự.',
            'tenant.email.email'                   => 'Email khách thuê không hợp lệ.',
            'tenant.email.unique'                  => 'Email này đã được sử dụng.',
            'tenant.phone.required'                => 'Số điện thoại khách thuê không được để trống.',
            'tenant.phone.regex'                   => 'Số điện thoại không hợp lệ (9–15 chữ số).',
            'tenant.id_card_number.required'       => 'Số CCCD/CMND không được để trống.',
            'tenant.id_card_number.unique'         => 'Số CCCD/CMND này đã tồn tại trong hệ thống.',
            'tenant.id_card_front_image.image'     => 'Ảnh mặt trước CCCD phải là file hình ảnh.',
            'tenant.id_card_front_image.mimes'     => 'Ảnh mặt trước CCCD chỉ chấp nhận: jpg, jpeg, png, webp.',
            'tenant.id_card_front_image.max'       => 'Ảnh mặt trước CCCD không được vượt quá 2MB.',
            'tenant.id_card_back_image.image'      => 'Ảnh mặt sau CCCD phải là file hình ảnh.',
            'tenant.id_card_back_image.mimes'      => 'Ảnh mặt sau CCCD chỉ chấp nhận: jpg, jpeg, png, webp.',
            'tenant.id_card_back_image.max'        => 'Ảnh mặt sau CCCD không được vượt quá 2MB.',
            'services.*.service_type'              => 'loại dịch vụ không hợp lệ.',
            'services.*.quantity'                  => 'số lượng phải là số nguyên và tối thiểu là 1.',
            'services.*.custom_price'              => 'giá thỏa thuận phải là số nguyên và không âm.',


        ];
    }

    public function attributes(): array
    {
        return [
            'room_id'                    => 'phòng',
            'start_date'                 => 'ngày bắt đầu',
            'billing_day'                => 'ngày thu tiền',
            'deposit'                    => 'tiền cọc',
            'room_price'                 => 'giá thuê phòng',
            'occupants_count'            => 'số lượng người ở',
            'electricity_reading'        => 'chỉ số điện ban đầu',
            'water_reading'              => 'chỉ số nước ban đầu',

            'electricity_image'          => 'ảnh đồng hồ điện',
            'water_image'                => 'ảnh đồng hồ nước',

            'tenant.full_name'           => 'họ và tên khách thuê',
            'tenant.email'               => 'email khách thuê',
            'tenant.phone'               => 'số điện thoại khách thuê',
            'tenant.id_card_number'      => 'số CCCD/CMND',
            'tenant.id_card_front_image' => 'ảnh mặt trước CCCD',
            'tenant.id_card_back_image'  => 'ảnh mặt sau CCCD',
            'services.*.service_type'    => 'loại dịch vụ',
            'services.*.quantity'        => 'số lượng',
            'services.*.custom_price'    => 'giá thỏa thuận',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tenant = $this->input('tenant', []);

        if (is_array($tenant)) {
            $this->merge([
                'tenant' => array_merge($tenant, [
                    'full_name'      => isset($tenant['full_name']) ? trim($tenant['full_name']) : null,
                    'email'          => isset($tenant['email']) ? strtolower(trim($tenant['email'])) : null,
                    'phone'          => isset($tenant['phone']) ? preg_replace('/\D/', '', $tenant['phone']) : null,
                    'id_card_number' => isset($tenant['id_card_number']) ? trim($tenant['id_card_number']) : null,
                ]),
            ]);
        }
    }
}
