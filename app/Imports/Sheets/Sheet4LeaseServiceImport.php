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

class Sheet4LeaseServiceImport implements ToCollection
{
    private int $userId;
    private int $successCount = 0;
    private int $failedCount = 0;
    private array $errors = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 2) continue; // Bỏ qua 2 dòng tiêu đề

            $actualRowNumber = $index + 1;
            $rowData = array_pad($row->toArray(), 5, null); // Đệm mảng 5 cột

            if (empty($rowData[0]) || empty($rowData[1])) continue;

            $validator = Validator::make($rowData, [
                '0' => ['required', 'string'],
                '1' => ['required', 'string'],
                '2' => ['required', 'string', 'in:Điện,Nước,Rác,Internet'],
                '3' => ['required', 'integer', 'min:1'],
                '4' => ['nullable', 'integer', 'min:0'],
            ], [
                'required' => 'Trường [:attribute] bắt buộc nhập.',
                'integer'  => 'Trường [:attribute] phải là số nguyên.',
                'in'       => 'Trường [:attribute] không hợp lệ.'
            ], [
                '0' => 'Mã khu nhà',
                '1' => 'Tên/Số phòng',
                '2' => 'Loại dịch vụ',
                '3' => 'Số lượng',
                '4' => 'Đơn giá riêng'
            ]);

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

        // 1. Dò tìm Khu nhà & Phòng
        $property = Property::where('user_id', $this->userId)->where('code', $propertyCode)->first();
        if (!$property) throw new \Exception("Không tìm thấy khu nhà '{$propertyCode}'.");

        $room = Room::where('property_id', $property->id)->where('name', $roomName)->first();
        if (!$room) throw new \Exception("Phòng '{$roomName}' không tồn tại.");

        // 2. Dò tìm Hợp đồng (Phải có khách thuê mới gán được dịch vụ)
        $lease = Lease::where('room_id', $room->id)->where('status', 'active')->first();
        if (!$lease) throw new \Exception("Phòng '{$roomName}' chưa có Hợp đồng (Khách đại diện). Không thể gán dịch vụ.");

        // 3. Mapping Loại dịch vụ sang Enum của DB
        $serviceMap = ['Điện' => 'electricity', 'Nước' => 'water', 'Rác' => 'garbage', 'Internet' => 'internet'];
        $serviceType = $serviceMap[trim((string)$data[2])];

        $quantity = (int)$data[3];
        $customPrice = ($data[4] !== null && $data[4] !== '') ? (int)$data[4] : null;

        // 4. Lưu hoặc Cập nhật dịch vụ
        LeaseServiceItem::updateOrCreate(
            ['lease_id' => $lease->id, 'service_type' => $serviceType],
            [
                'quantity'       => $quantity,
                'custom_price'   => $customPrice,
                'effective_date' => $lease->start_date
            ]
        );
    }

    private function addError(int $row, array $messages): void
    {
        $this->failedCount++;
        $this->errors[] = ['sheet' => 'Sheet 4 (Dịch vụ HĐ)', 'row' => $row, 'messages' => $messages];
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
