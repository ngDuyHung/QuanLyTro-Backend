<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\MeterType;
use App\Enums\RoomStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Services\TenantService;

class LeaseService
{
    /**
     * Tạo hợp đồng thuê mới (bao gồm tạo khách thuê mới):
     * 1. Kiểm tra phòng thuộc chủ trọ đang đăng nhập.
     * 2. Kiểm tra phòng đang ở trạng thái available.
     * 3. Kiểm tra phòng chưa có hợp đồng active.
     * 4. Tạo khách thuê từ dữ liệu gửi lên (kèm upload ảnh CCCD nếu có).
     * 5. Tạo hợp đồng, chuyển phòng sang occupied.
     * 6. Ghi chỉ số điện nước ban đầu.
     */
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    public function createLease(array $data, int $userId): Lease
    {
        // Ownership check: phòng phải thuộc khu nhà của chủ trọ đang đăng nhập
        $room = Room::whereHas('property', fn($q) => $q->where('user_id', $userId))
            ->findOrFail($data['room_id']);

        // Kiểm tra phòng đang ở trạng thái trống
        if (!$room->status->isAvailable()) {
            throw new BusinessException(
                "Phòng \"{$room->name}\" không ở trạng thái trống, không thể tạo hợp đồng."
            );
        }

        // Kiểm tra chưa tồn tại hợp đồng active cho phòng này
        if ($room->leases()->where('status', LeaseStatus::Active->value)->exists()) {
            throw new BusinessException("Phòng \"{$room->name}\" đang có hợp đồng thuê khác chưa kết thúc.");
        }

        return DB::transaction(function () use ($data, $room): Lease {

            // Tạo khách thuê mới
            $tenant = $this->tenantService->createTenant($data['tenant']);

            // Tạo hợp đồng thuê liên kết với khách thuê vừa tạo
            $lease = Lease::create([
                'room_id'     => $room->id,
                'tenant_id'   => $tenant->id,
                'start_date'  => $data['start_date'],
                'billing_day' => $data['billing_day'] ?? 1,
                'deposit'     => $data['deposit'] ?? 0,
                'status'      => LeaseStatus::Active->value,
            ]);

            // Chuyển trạng thái phòng sang occupied
            $room->update(['status' => RoomStatus::Occupied->value]);

            // Ghi chỉ số điện ban đầu (previous = 0, current = giá trị đọc lúc nhận phòng)
            MeterReading::create([
                'lease_id'         => $lease->id,
                'type'             => MeterType::Electricity->value,
                'previous_reading' => 0,
                'current_reading'  => $data['electricity_reading'],
                'reading_date'     => $data['start_date'],
            ]);

            // Ghi chỉ số nước ban đầu
            MeterReading::create([
                'lease_id'         => $lease->id,
                'type'             => MeterType::Water->value,
                'previous_reading' => 0,
                'current_reading'  => $data['water_reading'],
                'reading_date'     => $data['start_date'],
            ]);

            return $lease->load(['room.property', 'tenant']);
        });
    }

    /**
     * Kết thúc hợp đồng (trả phòng):
     * 1. Kiểm tra hợp đồng đang active.
     * 2. Cập nhật status = ended, end_date = hôm nay.
     * 3. Chuyển phòng về trạng thái available.
     */
    public function endLease(Lease $lease): Lease
    {
        // Kiểm tra hợp đồng đang active mới được kết thúc
        if (!$lease->status->isActive()) {
            throw new BusinessException('Hợp đồng đã kết thúc trước đó, không thể thực hiện lại.');
        }

        return DB::transaction(function () use ($lease): Lease {
            // Cập nhật hợp đồng
            $lease->update([
                'status'   => LeaseStatus::Ended->value,
                'end_date' => now()->toDateString(),
            ]);

            // Chuyển phòng về trạng thái trống
            $lease->room->update(['status' => RoomStatus::Available->value]);

            return $lease->fresh(['room.property', 'tenant']);
        });
    }
}
