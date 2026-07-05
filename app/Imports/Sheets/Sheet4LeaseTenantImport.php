<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Services\LeaseService;
use App\Models\Property;
use App\Models\Room;
use App\Models\Lease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet4LeaseTenantImport implements ToCollection
{
    private int $userId;
    private LeaseService $leaseService;
    private int $successCount = 0, $failedCount = 0;
    private array $errors = [], $propertyCache = [], $roomLeaseCache = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->leaseService = app(LeaseService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 2) continue;

            $actualRowNumber = $index + 1;
            $rowData = array_pad($row->toArray(), 15, null);

            if (empty($rowData[0]) || empty($rowData[1]) || empty($rowData[3])) continue;

            $validator = Validator::make(
                $rowData,
                [
                    '0' => ['required', 'string'],
                    '1' => ['required', 'string'],
                    '2' => ['required', 'string', 'in:Đại diện,Ở ghép'],
                    '3' => ['required', 'string', 'max:100'], // Họ tên
                    '4' => ['required', 'string', 'regex:/^[0-9]{9,15}$/'], // SĐT
                    '7' => ['required', 'date'], // Ngày bắt đầu
                    '9'  => ['required_if:2,Đại diện', 'nullable', 'integer', 'min:1'], // Số lượng người
                    '10' => ['required_if:2,Đại diện', 'nullable', 'integer', 'min:0'], // Giá chốt
                ],
                ['required_if' => '[:attribute] bắt buộc khi Vai trò là Đại diện.', 'integer' => '[:attribute] phải là số nguyên.'],
                ['0' => 'Mã khu', '1' => 'Tên Phòng', '2' => 'Vai trò', '3' => 'Họ tên', '4' => 'SĐT', '7' => 'Ngày bắt đầu HĐ', '9' => 'Số người', '10' => 'Giá HĐ']
            );

            if ($validator->fails()) {
                $this->addError($actualRowNumber, $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($rowData) {
                    $propertyCode = strtoupper(trim((string)$rowData[0]));
                    $roomName = trim((string)$rowData[1]);
                    $tenantRole = trim((string)$rowData[2]) === 'Ở ghép' ? 'member' : 'representative';

                    if (!isset($this->propertyCache[$propertyCode])) {
                        $property = Property::where('user_id', $this->userId)->where('code', $propertyCode)->first();
                        if (!$property) throw new \Exception("Không tìm thấy Mã khu '{$propertyCode}'.");
                        $this->propertyCache[$propertyCode] = $property->id;
                    }
                    $propertyId = $this->propertyCache[$propertyCode];

                    $roomCacheKey = "{$propertyId}_{$roomName}";

                    // Kịch bản: Ở ghép
                    if ($tenantRole === 'member') {
                        if (isset($this->roomLeaseCache[$roomCacheKey])) {
                            $roomId = $this->roomLeaseCache[$roomCacheKey]['room_id'];
                            $leaseId = $this->roomLeaseCache[$roomCacheKey]['lease_id'];
                        } else {
                            $room = Room::where('property_id', $propertyId)->where('name', $roomName)->first();
                            if (!$room) throw new \Exception("Phòng '{$roomName}' không tồn tại.");

                            $activeLease = Lease::where('room_id', $room->id)->where('status', 'active')->first();
                            if (!$activeLease) throw new \Exception("Phòng '{$roomName}' chưa có hợp đồng đại diện nào. Dòng khách đại diện phải nằm trên khách ở ghép.");
                            $roomId = $room->id;
                            $leaseId = $activeLease->id;
                        }

                        $mappedRoommateData = [
                            'tenant_full_name'      => $rowData[3],
                            'tenant_phone'          => $rowData[4],
                            'tenant_id_card_number' => $rowData[5],
                            'tenant_email'          => $rowData[6],
                            'lease_start_date'      => \Carbon\Carbon::parse($rowData[7])->format('Y-m-d'),
                        ];
                        $this->leaseService->addRoommateFromImport($roomId, $leaseId, $mappedRoommateData);

                        // Kịch bản: Đại diện
                    } else {
                        $room = Room::where('property_id', $propertyId)->where('name', $roomName)->first();
                        if (!$room) throw new \Exception("Phòng '{$roomName}' không tồn tại. Hãy khai báo bên Sheet 2 trước.");

                        $mappedLeaseData = [
                            'tenant_full_name'          => $rowData[3],
                            'tenant_phone'              => $rowData[4],
                            'tenant_id_card_number'     => $rowData[5],
                            'tenant_email'              => $rowData[6],
                            'lease_start_date'          => \Carbon\Carbon::parse($rowData[7])->format('Y-m-d'),
                            'lease_billing_day'         => $rowData[8],
                            'occupants_count'           => $rowData[9],
                            'lease_room_price'          => $rowData[10],
                            'lease_deposit'             => $rowData[11],
                            'lease_electricity_reading' => $rowData[12],
                            'lease_water_reading'       => $rowData[13],
                        ];

                        $lease = $this->leaseService->createFromImport($room->id, $mappedLeaseData);
                        $room->update(['status' => 'occupied']);

                        $this->roomLeaseCache[$roomCacheKey] = ['room_id' => $room->id, 'lease_id' => $lease->id];
                    }
                });
                $this->successCount++;
            } catch (Throwable $e) {
                $this->addError($actualRowNumber, [$e->getMessage()]);
            }
        }
    }

    private function addError(int $row, array $messages): void
    {
        $this->failedCount++;
        $this->errors[] = ['sheet' => 'Sheet 4 (Hợp đồng & Khách)', 'row' => $row, 'messages' => $messages];
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
}
