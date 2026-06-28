<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\MeterType;
use App\Enums\RoomStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\LeaseMember;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\RoomResident;
use Illuminate\Support\Facades\DB;
use Throwable;

class LeaseService
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    /**
     * Tạo hợp đồng mới kèm khách đại diện và chỉ số ban đầu.
     */
    public function createLease(array $data, int $userId): Lease
    {
        $createdTenantId = null;

        try {
            return DB::transaction(function () use ($data, $userId, &$createdTenantId): Lease {
                $room = Room::whereHas(
                    'property',
                    fn($query) => $query->where('user_id', $userId)
                )->findOrFail($data['room_id']);

                if (!$room->status->isAvailable()) {
                    throw new BusinessException(
                        "Phòng \"{$room->name}\" không ở trạng thái trống, không thể tạo hợp đồng."
                    );
                }

                if ($room->leases()->where('status', LeaseStatus::Active->value)->exists()) {
                    throw new BusinessException("Phòng \"{$room->name}\" đang có hợp đồng thuê khác chưa kết thúc.");
                }

                $tenant = $this->tenantService->createProfile($data['tenant']);
                $createdTenantId = $tenant->id;

                $lease = Lease::create([
                    'room_id' => $room->id,
                    'tenant_id' => $tenant->id,
                    'start_date' => $data['start_date'],
                    'billing_day' => $data['billing_day'] ?? 1,
                    'deposit' => $data['deposit'] ?? 0,
                    'status' => LeaseStatus::Active->value,
                ]);

                $this->tenantService->createRepresentativeResidence(
                    tenant: $tenant,
                    lease: $lease,
                    moveInDate: $data['start_date'],
                    note: 'Người đứng tên hợp đồng.'
                );

                $room->update([
                    'status' => RoomStatus::Occupied->value,
                ]);

                // Lưu ảnh điện (nếu có)
                $electricityImagePath = null;
                if (isset($data['electricity_image']) && $data['electricity_image'] instanceof \Illuminate\Http\UploadedFile) {
                    $ext = $data['electricity_image']->extension();
                    $electricityImagePath = $data['electricity_image']->storeAs("utilities/lease_{$lease->id}", "electricity_" . time() . ".{$ext}", 'public');
                }

                // Tạo chỉ số ban đầu cho hợp đồng mới cho điện và nước bằng giá trị current_reading = previous_reading = chỉ số đầu vào từ request
                // vì mới vào nếu phải cho nó bằng nhau để tính ra 0đ hóa đơn vì thu đầu vào không tính điện nước. 
                MeterReading::create([
                    'lease_id' => $lease->id,
                    'type' => MeterType::Electricity->value,
                    'previous_reading' => $data['electricity_reading'], //Cho previous_reading = current_reading 
                    'current_reading' => $data['electricity_reading'],
                    'reading_date' => $data['start_date'],
                    'meter_image' => $electricityImagePath,
                    'note' => 'Chỉ số điện ban đầu khi nhận phòng.',
                ]);

                // Lưu ảnh nước (nếu có)
                $waterImagePath = null;
                if (isset($data['water_image']) && $data['water_image'] instanceof \Illuminate\Http\UploadedFile) {
                    $ext = $data['water_image']->extension();
                    $waterImagePath = $data['water_image']->storeAs("utilities/lease_{$lease->id}", "water_" . time() . ".{$ext}", 'public');
                }

                MeterReading::create([
                    'lease_id' => $lease->id,
                    'type' => MeterType::Water->value,
                    'previous_reading' => $data['water_reading'], //Cho previous_reading = current_reading
                    'current_reading' => $data['water_reading'],
                    'reading_date' => $data['start_date'],
                    'meter_image' => $waterImagePath,
                    'note' => 'Chỉ số nước ban đầu khi nhận phòng.',
                ]);

                // Tạo các dịch vụ kèm theo hợp đồng nếu có
                if (!empty($data['services'])) {
                    foreach ($data['services'] as $service) {
                        $lease->serviceItems()->create([
                            'service_type' => $service['service_type'],
                            'quantity'     => $service['quantity'],
                            'custom_price' => $service['custom_price'] ?? null,
                        ]);
                    }
                }
                // ------------------------------------

                return $lease->load([
                    'room.property',
                    'tenant.currentResidence.room.property',
                    'tenant.currentResidence.lease',
                    'serviceItems',
                ]);
            });
        } catch (Throwable $exception) {
            if ($createdTenantId) {
                $this->tenantService->deleteTenantFiles($createdTenantId);
            }

            throw $exception;
        }
    }

    /**
     * Kết thúc hợp đồng, chuyển phòng về trống và cho toàn bộ khách rời phòng.
     */
    public function endLease(Lease $lease): Lease
    {
        if (!$lease->status->isActive()) {
            throw new BusinessException('Hợp đồng đã kết thúc trước đó, không thể thực hiện lại.');
        }

        return DB::transaction(function () use ($lease): Lease {
            $date = now()->toDateString();

            $lease->update([
                'status' => LeaseStatus::Ended->value,
                'end_date' => $date,
            ]);

            RoomResident::where('lease_id', $lease->id)
                ->whereIn('status', ['pending', 'active'])
                ->update([
                    'status' => 'left',
                    'move_out_date' => $date,
                ]);

            LeaseMember::where('lease_id', $lease->id)
                ->whereNull('move_out_date')
                ->update([
                    'move_out_date' => $date,
                ]);

            $lease->room->update([
                'status' => RoomStatus::Available->value,
            ]);

            return $lease->fresh(['room.property', 'tenant']);
        });
    }
}
