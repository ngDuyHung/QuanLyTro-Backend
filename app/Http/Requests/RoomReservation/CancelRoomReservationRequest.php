<?php

declare(strict_types=1);

namespace App\Http\Requests\RoomReservation;

use Illuminate\Foundation\Http\FormRequest;

class CancelRoomReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:255'],
            'refund_amount' => ['nullable', 'integer', 'min:0'], // Số tiền hoàn trả (nếu có)
            'payment_method' => ['required_with:refund_amount', 'in:cash,bank_transfer'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'Lý do hủy đặt cọc không được để trống.',
            'cancel_reason.max' => 'Lý do hủy đặt cọc không vượt quá 255 ký tự.',
            'refund_amount.integer' => 'Số tiền hoàn trả phải là số nguyên.',
            'refund_amount.min' => 'Số tiền hoàn trả không được âm.',
            'payment_method.required_with' => 'Phương thức thanh toán phải được cung cấp khi có số tiền hoàn trả.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
            'bank_account_id.exists' => 'Tài khoản ngân hàng không tồn tại.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cancel_reason' => 'Lý do hủy đặt cọc',
            'refund_amount' => 'Số tiền hoàn trả',
            'payment_method' => 'Phương thức thanh toán',
            'bank_account_id' => 'Tài khoản ngân hàng',
        ];
    }
}