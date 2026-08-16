<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Models\Property;
use App\Services\RoomService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet2RoomImport implements ToCollection
{
    private int $userId;
    private RoomService $roomService;
    private int $successCount = 0, $failedCount = 0;
    private array $errors = [], $propertyCache = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->roomService = app(RoomService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 2) continue;

            $actualRowNumber = $index + 1;
            $rowData = array_pad($row->toArray(), 12, null);

            if (empty($rowData[0]) || empty($rowData[1])) continue;

            $validator = Validator::make(
                $rowData,
                [
                    '0' => ['required', 'string'],
                    '1' => ['required', 'string'],
                    '2' => ['required', 'integer', 'min:0'],
                    '3' => ['nullable', 'integer'],
                    '5' => ['required', 'string', 'in:Còn trống,Đang bảo trì,Đã cho thuê,Đã đặt cọc'],
                    '11' => ['nullable', 'integer', 'min:0'],
                ],
                ['required' => '[:attribute] bắt buộc nhập.', 'integer' => '[:attribute] phải là số nguyên.'],
                ['0' => 'Mã khu nhà', '1' => 'Tên phòng', '2' => 'Giá thuê', '3' => 'Tiền cọc', '5' => 'Trạng thái phòng', '11' => 'Thứ tự']
            );

            if ($validator->fails()) {
                $this->addError($actualRowNumber, $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($rowData) {
                    $propertyCode = strtoupper(trim((string)$rowData[0]));
                    if (!isset($this->propertyCache[$propertyCode])) {
                        $property = Property::where('user_id', $this->userId)->where('code', $propertyCode)->first();
                        if (!$property) throw new \Exception("Không tìm thấy Mã khu '{$propertyCode}'. Hãy kiểm tra Sheet 1.");
                        $this->propertyCache[$propertyCode] = $property->id;
                    }

                    // Logic xử lý tầng: "Trệt" -> 0
                    $floorInput = trim((string)$rowData[4]);
                    $floorNumber = (strtolower($floorInput) === 'trệt') ? 0 : (int)$floorInput;

                    $statusMap = ['Còn trống' => 'available', 'Đang bảo trì' => 'maintenance', 'Đã cho thuê' => 'occupied', 'Đã đặt cọc' => 'reserved'];
                    $mappedData = [
                        'room_name'          => $rowData[1],
                        'room_current_price' => $rowData[2],
                        'room_deposit'       => $rowData[3],
                        'room_floor_number'  => $floorNumber,
                        'room_status'        => $statusMap[trim((string)$rowData[5])] ?? 'available',
                        'room_area'          => $rowData[6],
                        'room_max_occupants' => $rowData[7],
                        'room_billing_day'   => $rowData[8],
                        'room_allow_shared'  => (trim((string)$rowData[9]) === 'Có') ? 1 : 0,
                        'room_is_public'     => (trim((string)$rowData[10]) === 'Có') ? 1 : 0,
                        'room_sort_order'    => !is_null($rowData[11]) ? (int)$rowData[11] : 0,
                    ];

                    $this->roomService->createFromImport($this->propertyCache[$propertyCode], $this->userId, $mappedData, false);
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
        $this->errors[] = ['sheet' => 'Sheet 2 (Phòng)', 'row' => $row, 'messages' => $messages];
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
