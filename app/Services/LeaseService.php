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
use App\Models\Tenant;
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
                    'room_price' => $data['room_price'] ?? 0,
                    'occupants_count' => $data['occupants_count'] ?? 1,
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

    /**
     * Khởi tạo hợp đồng và lưu vết cư dân đại diện ban đầu
     */
    public function createFromImport(int $roomId, array $data): Lease
    {
        $tenant = Tenant::updateOrCreate(
            ['id_card_number' => trim((string)$data['tenant_id_card_number'])],
            [
                'full_name' => trim((string)$data['tenant_full_name']),
                'phone'     => preg_replace('/\D/', '', (string)$data['tenant_phone']),
                'email'     => !empty($data['tenant_email']) ? strtolower(trim((string)$data['tenant_email'])) : null,
            ]
        );

        $deposit = $data['lease_deposit'] ?? $data['room_current_price'];

        $lease = Lease::create([
            'room_id'     => $roomId,
            'tenant_id'   => $tenant->id,
            'start_date'  => $data['lease_start_date'],
            'billing_day' => $data['lease_billing_day'] ?? 1,
            'room_price'  => $data['lease_room_price'],
            'occupants_count' => $data['occupants_count'] ?? 1,
            'deposit'     => $deposit,
            'status'      => 'active',
        ]);

        // Đã sửa lại đúng cấu trúc trường của bảng lease_members trong SQL (dùng relationship thay vì role)
        $lease->members()->create([
            'tenant_id'    => $tenant->id,
            'relationship' => 'other',
            'move_in_date' => $data['lease_start_date'],
        ]);

        \App\Models\RoomResident::create([
            'room_id'      => $roomId,
            'tenant_id'    => $tenant->id,
            'lease_id'     => $lease->id,
            'role'         => 'representative', // Người đại diện đứng tên phòng
            'status'       => 'active',
            'move_in_date' => $data['lease_start_date'],
            'note'         => 'Hồ sơ cư trú đại diện được tạo tự động từ hệ thống Import Excel.',
        ]);

        \App\Models\MeterReading::create([
            'lease_id'         => $lease->id,
            'type'             => 'electricity',
            'previous_reading' => (int)$data['lease_electricity_reading'],
            'current_reading'  => (int)$data['lease_electricity_reading'],
            'reading_date'     => $data['lease_start_date'],
            'note'             => 'Chỉ số điện ban đầu (Import)',
        ]);

        \App\Models\MeterReading::create([
            'lease_id'         => $lease->id,
            'type'             => 'water',
            'previous_reading' => (int)$data['lease_water_reading'],
            'current_reading'  => (int)$data['lease_water_reading'],
            'reading_date'     => $data['lease_start_date'],
            'note'             => 'Chỉ số nước ban đầu (Import)',
        ]);

        /* |--------------------------------------------------------------------------
        | BỔ SUNG: TỰ ĐỘNG GÁN DỊCH VỤ ĐI KÈM CHO HỢP ĐỒNG IMPORT
        | Lấy giá dịch vụ ưu tiên của khu nhà, nếu không có lấy giá global mặc định
        |--------------------------------------------------------------------------
        */
        $room = \App\Models\Room::find($roomId);

        if ($room) {
            // 1. Lấy giá riêng của khu nhà
            $propertyPrices = \App\Models\ServicePrice::where('property_id', $room->property_id)
                ->get()
                ->keyBy(fn($p) => $p->service_type->value ?? $p->service_type);

            // 2. Lấy giá mặc định (global) cho các loại DV chưa có giá riêng
            $assignedTypes = $propertyPrices->keys()->all();
            $globalPrices = \App\Models\ServicePrice::whereNull('property_id')
                ->when(!empty($assignedTypes), fn($q) => $q->whereNotIn('service_type', $assignedTypes))
                ->get()
                ->keyBy(fn($p) => $p->service_type->value ?? $p->service_type);

            // 3. Kết hợp lại thành danh sách dịch vụ áp dụng cho phòng này
            $mergedPrices = $propertyPrices->merge($globalPrices)->values();

            // 4. Gắn toàn bộ vào hợp đồng mới tạo
            foreach ($mergedPrices as $service) {
                $lease->serviceItems()->create([
                    'service_type' => $service->service_type,
                    'quantity'     => 1,    // Mặc định gán số lượng là 1 khi import
                    'custom_price' => $service->unit_price, // Null để hệ thống tự mapping với bảng giá gốc
                ]);
            }
        }

        return $lease;
    }

    /**
     *  HÀM Xử lý thêm Khách Ở Ghép vào Hợp đồng và Phòng đang vận hành
     */
    public function addRoommateFromImport(int $roomId, int $leaseId, array $data): void
    {
        // 1. Tạo hoặc cập nhật thông tin hồ sơ của người ở ghép
        $tenant = Tenant::updateOrCreate(
            ['id_card_number' => trim((string)$data['tenant_id_card_number'])],
            [
                'full_name' => trim((string)$data['tenant_full_name']),
                'phone'     => preg_replace('/\D/', '', (string)$data['tenant_phone']),
                'email'     => !empty($data['tenant_email']) ? strtolower(trim((string)$data['tenant_email'])) : null,
            ]
        );

        // Chặn trùng lặp: Kiểm tra xem người này đã được add vào trạng thái active trong phòng này chưa
        $residentExists = \App\Models\RoomResident::where('room_id', $roomId)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->exists();

        if ($residentExists) {
            throw new \Exception("Khách ở ghép '{$tenant->full_name}' bị trùng lặp thông tin dữ liệu trong cùng một phòng.");
        }

        $lease = Lease::findOrFail($leaseId);

        // 2. Lưu vết thông tin vào bảng thành viên hợp đồng lease_members (Mối quan hệ là bạn bè/ở ghép)
        $lease->members()->create([
            'tenant_id'    => $tenant->id,
            'relationship' => 'friend',
            'move_in_date' => $data['lease_start_date'],
        ]);

        // 3. Thiết lập mối quan hệ cư trú thực tế trong bảng room_residents với vai trò 'member'
        \App\Models\RoomResident::create([
            'room_id'      => $roomId,
            'tenant_id'    => $tenant->id,
            'lease_id'     => $leaseId,
            'role'         => 'member', // Phân luồng chính xác: Là thành viên ở ghép (member) chứ không phải đại diện
            'status'       => 'active', // Trạng thái hoạt động trực tiếp trong phòng
            'move_in_date' => $data['lease_start_date'],
            'note'         => 'Thành viên ở ghép được đồng bộ tự động từ hệ thống Import Excel.',
        ]);
    }
}
