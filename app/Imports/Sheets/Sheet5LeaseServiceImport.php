<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Models\Property;
use App\Models\Room;
use App\Models\Lease;
use App\Models\LeaseServiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet5LeaseServiceImport implements ToCollection
{
    private int $userId;
    private int $successCount = 0, $failedCount = 0;
    private array $errors = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 2) continue;

            $actualRowNumber = $index + 1;
            $rowData = array_pad($row->toArray(), 5, null);

            if (empty($rowData[0]) || empty($rowData[1])) continue;

            $validator = Validator::make(
                $rowData,
                [
                    '0' => ['required', 'string'],
                    '1' => ['required', 'string'],
                    '2' => ['required', 'string', 'in:Điện,Nước,Rác,Internet'],
                    '3' => ['required', 'integer', 'min:1'],
                    '4' => ['nullable', 'integer', 'min:0'],
                ],
                ['required' => '[:attribute] bắt buộc nhập.', 'integer' => '[:attribute] phải là số nguyên.'],
                ['0' => 'Mã khu nhà', '1' => 'Tên Phòng', '2' => 'Loại dịch vụ', '3' => 'Số lượng', '4' => 'Đơn giá riêng']
            );

            if ($validator->fails()) {
                $this->addError($actualRowNumber, $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($rowData) {
                    $propertyCode = strtoupper(trim((string)$rowData[0]));
                    $roomName = trim((string)$rowData[1]);

                    $property = Property::where('user_id', $this->userId)->where('code', $propertyCode)->first();
                    if (!$property) throw new \Exception("Không tìm thấy Mã khu '{$propertyCode}'.");

                    $room = Room::where('property_id', $property->id)->where('name', $roomName)->first();
                    if (!$room) throw new \Exception("Phòng '{$roomName}' không tồn tại.");

                    $lease = Lease::where('room_id', $room->id)->where('status', 'active')->first();
                    if (!$lease) throw new \Exception("Phòng '{$roomName}' chưa có Hợp đồng (Khách đại diện). Không thể gán dịch vụ.");

                    $serviceMap = ['Điện' => 'electricity', 'Nước' => 'water', 'Rác' => 'garbage', 'Internet' => 'internet'];
                    $serviceType = $serviceMap[trim((string)$rowData[2])];
                    $customPrice = ($rowData[4] !== null && $rowData[4] !== '') ? (int)$rowData[4] : null;

                    LeaseServiceItem::updateOrCreate(
                        ['lease_id' => $lease->id, 'service_type' => $serviceType],
                        [
                            'quantity'       => (int)$rowData[3],
                            'custom_price'   => $customPrice,
                            'effective_date' => clone $lease->start_date // Sử dụng ngày bắt đầu HĐ làm ngày hiệu lực
                        ]
                    );
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
        $this->errors[] = ['sheet' => 'Sheet 5 (Dịch vụ riêng)', 'row' => $row, 'messages' => $messages];
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
