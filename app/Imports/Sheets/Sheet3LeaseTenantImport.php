<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Services\LeaseService;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet3LeaseTenantImport implements ToCollection
{
    private int $userId;
    private LeaseService $leaseService;

    private int $successCount = 0;
    private int $failedCount = 0;
    private array $errors = [];

    private array $propertyCache = [];
    private array $roomLeaseCache = []; // Cache hợp đồng đại diện cho người ở ghép

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->leaseService = app(LeaseService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Bỏ qua 2 dòng đầu
            if ($index < 2) continue;

            $actualRowNumber = $index + 1;

            // Sheet 3 rất dài, đệm mảng lên 25 phần tử cho an toàn
            $rowData = array_pad($row->toArray(), 25, null);

            if (empty($rowData[0]) || empty($rowData[1]) || empty($rowData[3])) continue;

            $validator = Validator::make(
                $rowData,
                $this->validationRules($rowData),
                $this->validationMessages(),
                $this->customAttributes()
            );

            if ($validator->fails()) {
                $this->addError($actualRowNumber, $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($rowData) {
                    $this->processRow($rowData);
                });
                $this->successCount++;
            } catch (Throwable $e) {
                $this->addError($actualRowNumber, [$e->getMessage()]);
            }
        }
    }

    private function processRow(array $data): void
    {
        $propertyCode = strtoupper(trim((string)$data[0]));
        $roomName = trim((string)$data[1]);
        $tenantRole = trim((string)$data[2]) === 'Ở ghép' ? 'member' : 'representative';

        // 1. Tìm Khu nhà & Phòng
        if (!isset($this->propertyCache[$propertyCode])) {
            $property = Property::where('user_id', $this->userId)->where('code', $propertyCode)->first();
            if (!$property) throw new \Exception("Không tìm thấy khu nhà '{$propertyCode}'.");
            $this->propertyCache[$propertyCode] = $property->id;
        }
        $propertyId = $this->propertyCache[$propertyCode];

        $roomCacheKey = "{$propertyId}_{$roomName}";

        // 2. Xử lý kịch bản: Khách Ở Ghép
        if ($tenantRole === 'member') {
            $roomId = null;
            $leaseId = null;

            if (isset($this->roomLeaseCache[$roomCacheKey])) {
                $roomId = $this->roomLeaseCache[$roomCacheKey]['room_id'];
                $leaseId = $this->roomLeaseCache[$roomCacheKey]['lease_id'];
            } else {
                $room = Room::where('property_id', $propertyId)->where('name', $roomName)->first();
                if (!$room) throw new \Exception("Phòng '{$roomName}' không tồn tại.");

                $activeLease = \App\Models\Lease::where('room_id', $room->id)->where('status', 'active')->first();
                if (!$activeLease) throw new \Exception("Phòng '{$roomName}' chưa có hợp đồng đại diện nào.");

                $roomId = $room->id;
                $leaseId = $activeLease->id;
            }

            $mappedRoommateData = [
                'tenant_full_name'      => $data[3],
                'tenant_phone'          => $data[4],
                'tenant_id_card_number' => $data[5],
                'tenant_email'          => $data[6],
                'lease_start_date'      => $data[7],
            ];
            $this->leaseService->addRoommateFromImport($roomId, $leaseId, $mappedRoommateData, $this->userId);

            // 3. Xử lý kịch bản: Khách Đại Diện
        } else {
            $room = Room::where('property_id', $propertyId)->where('name', $roomName)->first();
            if (!$room) throw new \Exception("Phòng '{$roomName}' không tồn tại. Vui lòng khai báo bên Sheet 1 trước.");

            // Chuẩn bị dữ liệu để truyền qua LeaseService
            $mappedLeaseData = [
                'tenant_full_name'          => $data[3],
                'tenant_phone'              => $data[4],
                'tenant_id_card_number'     => $data[5],
                'tenant_email'              => $data[6],
                'lease_start_date'          => $data[7],
                'lease_billing_day'         => $data[8],
                'occupants_count'           => $data[9],
                'lease_room_price'          => $data[10],
                'lease_deposit'             => $data[11],
                'lease_electricity_reading' => $data[12],
                'lease_water_reading'       => $data[13],

                // MẢNG DỊCH VỤ RIÊNG (Lấy từ 4 cột cuối)
                'custom_services' => [
                    'electricity' => $data[14] ?? null,
                    'water'       => $data[15] ?? null,
                    'garbage'     => $data[16] ?? null,
                    'internet'    => $data[17] ?? null,
                ]
            ];

            $lease = $this->leaseService->createFromImport($room->id, $mappedLeaseData, $this->userId);

            // Cập nhật trạng thái phòng thành Đã cho thuê
            $room->update(['status' => 'occupied']);

            // Lưu cache để nếu dòng dưới là người ở ghép thì add luôn
            $this->roomLeaseCache[$roomCacheKey] = [
                'room_id'  => $room->id,
                'lease_id' => $lease->id
            ];
        }
    }

    private function addError(int $row, array $messages): void
    {
        $this->failedCount++;
        $this->errors[] = ['sheet' => 'Sheet 3 (Hợp đồng)', 'row' => $row, 'messages' => $messages];
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validationRules(array $data): array
    {
        return [
            '0' => ['required', 'string'],
            '1' => ['required', 'string'],
            '2' => ['required', 'string', 'in:Đại diện,Ở ghép'],
            '3' => ['required', 'string', 'max:100'],
            '4' => ['required', 'string', 'regex:/^[0-9]{9,15}$/'],
            '5' => ['nullable', 'string', 'max:20'],
            '7' => ['required', 'date'],
            // Rule bắt buộc nếu là Đại diện
            '9'  => ['required_if:2,Đại diện', 'nullable', 'integer', 'min:1'],
            '10' => ['required_if:2,Đại diện', 'nullable', 'integer', 'min:0'],
            '11' => ['nullable', 'integer', 'min:0'],
            '12' => ['required_if:2,Đại diện', 'nullable', 'integer', 'min:0'],
            '13' => ['required_if:2,Đại diện', 'nullable', 'integer', 'min:0'],
            // Các cột dịch vụ riêng lẻ
            '14' => ['nullable', 'integer', 'min:0'],
            '15' => ['nullable', 'integer', 'min:0'],
            '16' => ['nullable', 'integer', 'min:0'],
            '17' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function validationMessages(): array
    {
        return ['required' => 'Trường [:attribute] bắt buộc nhập.', 'required_if' => 'Trường [:attribute] bắt buộc khi là Đại diện.', 'integer' => 'Phải là số nguyên.'];
    }
    private function customAttributes(): array
    {
        return ['0' => 'Mã khu', '1' => 'Phòng', '2' => 'Vai trò', '3' => 'Họ tên', '4' => 'SĐT', '9' => 'Số người', '10' => 'Giá HĐ', '12' => 'Số điện', '13' => 'Số nước'];
    }
}
