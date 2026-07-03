<?php

declare(strict_types=1);

namespace App\Http\Requests\RoomReservation;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class StoreRoomReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Phân quyền sẽ check qua ID phòng
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'tenant_name' => ['required', 'string', 'max:100'],
            'tenant_phone' => ['required', 'string', 'regex:/^[0-9]{9,15}$/'],
            'deposit_amount' => ['required', 'integer', 'min:1000'],
            'expected_move_in_date' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            
            // Các trường để tự động sinh Phiếu thu
            'payment_method' => ['required', 'in:cash,bank_transfer'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.exists' => 'Phòng không tồn tại hoặc không thuộc quyền quản lý của bạn.',
            'tenant_name.required' => 'Tên người thuê không được để trống.',
            'tenant_name.max' => 'Tên người thuê không vượt quá 100 ký tự.',
            'tenant_phone.required' => 'Số điện thoại không được để trống.',
            'tenant_phone.regex' => 'Số điện thoại không hợp lệ. Vui lòng nhập từ 9 đến 15 chữ số.',
            'deposit_amount.required' => 'Số tiền cọc không được để trống.',
            'deposit_amount.min' => 'Số tiền cọc phải lớn hơn hoặc bằng 1000.',
            'expected_move_in_date.required' => 'Ngày dự kiến chuyển đến không được để trống.',
            'expected_move_in_date.date' => 'Ngày dự kiến chuyển đến không hợp lệ.',
            'expected_move_in_date.after_or_equal' => 'Ngày dự kiến chuyển đến phải là ngày hiện tại hoặc ngày sau.',
            'payment_method.required' => 'Phương thức thanh toán không được để trống.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => 'Phòng',
            'tenant_name' => 'Tên người thuê',
            'tenant_phone' => 'Số điện thoại',
            'deposit_amount' => 'Số tiền cọc',
            'expected_move_in_date' => 'Ngày dự kiến chuyển đến',
            'note' => 'Ghi chú',
            'payment_method' => 'Phương thức thanh toán',
            'bank_account_id' => 'Tài khoản ngân hàng',
        ];
    }
}