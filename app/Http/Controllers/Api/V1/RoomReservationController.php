<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomReservation\StoreRoomReservationRequest;
use App\Http\Requests\RoomReservation\CancelRoomReservationRequest;
use App\Http\Requests\RoomReservation\ExtendRoomReservationRequest;
use App\Http\Resources\RoomReservation\RoomReservationResource;
use App\Models\RoomReservation;
use App\Services\RoomReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomReservationController extends Controller
{
    public function __construct(
        private readonly RoomReservationService $reservationService
    ) {}

    /**
     * Tạo phiếu giữ chỗ
     */
    public function store(StoreRoomReservationRequest $request): JsonResponse
    {
        $reservation = $this->reservationService->createReservation(
            data: $request->validated(),
            userId: $request->user()->id
        );

        return (new RoomReservationResource($reservation->load('room')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Hủy đặt cọc (Kèm xử lý hoàn tiền)
     */
    public function cancel(CancelRoomReservationRequest $request, int $roomId): JsonResponse
    {
        $reservation = RoomReservation::with('room.property')
            ->whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->where('room_id', $roomId)
            ->where('status', 'pending')
            ->firstOrFail();

        $reservation = $this->reservationService->cancelReservation(
            reservation: $reservation,
            data: $request->validated(),
            userId: $request->user()->id
        );

        return (new RoomReservationResource($reservation))->response();
    }

    /**
     * Gia hạn ngày nhận phòng
     */
    public function extend(ExtendRoomReservationRequest $request, int $id): JsonResponse
    {
        $reservation = RoomReservation::with('room')
            ->whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $reservation->update([
            'expected_move_in_date' => $request->validated('expected_move_in_date')
        ]);

        return (new RoomReservationResource($reservation))->response();
    }
}
