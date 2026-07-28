<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Room;
use App\Models\RoomReservation;
use Illuminate\Support\Facades\DB;
use Throwable;

class RoomReservationService
{
    public function __construct(
        private readonly FinancialTransactionService $financialTransactionService
    ) {}

    /**
     * Tạo phiếu giữ chỗ + Tự động sinh phiếu thu
     */
    public function createReservation(array $data, int $userId): RoomReservation
    {
        return DB::transaction(function () use ($data, $userId) {
            $room = Room::whereHas('property', fn($q) => $q->where('user_id', $userId))
                ->findOrFail($data['room_id']);

           if ($room->status !== \App\Enums\RoomStatus::Available) {
                throw new BusinessException('Phòng không ở trạng thái trống để đặt cọc.');
            }

            // 1. Tạo phiếu đặt cọc
            $reservation = RoomReservation::create([
                'room_id' => $room->id,
                'tenant_name' => $data['tenant_name'],
                'tenant_phone' => $data['tenant_phone'],
                'deposit_amount' => $data['deposit_amount'],
                'expected_move_in_date' => $data['expected_move_in_date'],
                'note' => $data['note'] ?? null,
                'status' => 'pending',
            ]);

            // 2. Chuyển trạng thái phòng
            $room->update(['status' => 'reserved']);

            // 3. Tự động sinh Phiếu thu
            $this->financialTransactionService->createGeneralTransaction([
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'direction' => 'income', // tiền vào
                'category' => 'holding_deposit',
                'accounting_type' => 'liability_in', // Tiền giữ hộ
                'amount' => $reservation->deposit_amount,
                'method' => $data['payment_method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'transaction_date' => now()->toDateTimeString(),
                'description' => "Thu tiền cọc giữ chỗ phòng {$room->name} - {$reservation->tenant_name}",
            ], $userId);

            return $reservation;
        });
    }

    /**
     * Hủy giữ chỗ + Xử lý hoàn tiền (nếu có)
     */
    public function cancelReservation(RoomReservation $reservation, array $data, int $userId): RoomReservation
    {
        if ($reservation->status !== 'pending') {
            throw new BusinessException('Chỉ có thể hủy phiếu giữ chỗ đang ở trạng thái chờ.');
        }

        return DB::transaction(function () use ($reservation, $data, $userId) {
            $room = $reservation->room;

            // 1. Cập nhật phiếu & phòng
            $reservation->update(['status' => 'cancelled', 'note' => $reservation->note . " | Lý do hủy: " . $data['cancel_reason']]);
            $room->update(['status' => 'available']);

            // 2. Nếu có hoàn tiền, tự động sinh Phiếu chi
            $refundAmount = (int) ($data['refund_amount'] ?? 0);
            if ($refundAmount > 0) {
                if ($refundAmount > $reservation->deposit_amount) {
                    throw new BusinessException('Số tiền hoàn không được lớn hơn số tiền đã cọc.');
                }

                $this->financialTransactionService->createGeneralTransaction([
                    'property_id' => $room->property_id,
                    'room_id' => $room->id,
                    'direction' => 'expense',
                    // Sử dụng other_expense hoặc refund_security_deposit tùy theo cấu hình DB
                    'category' => 'refund_security_deposit', 
                    'accounting_type' => 'liability_out',
                    'amount' => $refundAmount,
                    'method' => $data['payment_method'],
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'transaction_date' => now()->toDateTimeString(),
                    'description' => "Hoàn trả tiền cọc giữ chỗ phòng {$room->name} - {$reservation->tenant_name}",
                ], $userId);
            }

            return $reservation;
        });
    }

    /**
     * Chốt cọc (Khi khách dọn vào và lập hợp đồng)
     */
    public function completeReservation(RoomReservation $reservation): void
    {
        if ($reservation->status === 'pending') {
            $reservation->update(['status' => 'completed']);
        }
    }
}