<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Services\PropertyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet1PropertyImport implements ToCollection
{
    private int $userId;
    private PropertyService $propertyService;
    private int $successCount = 0, $failedCount = 0;
    private array $errors = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->propertyService = app(PropertyService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 2) continue; // Bỏ qua 2 dòng đầu

            $actualRowNumber = $index + 1;
            $rowData = array_pad($row->toArray(), 10, null); // Đệm 10 cột

            if (empty($rowData[0])) continue;

            $validator = Validator::make(
                $rowData,
                [
                    '0' => ['required', 'string', 'max:50'], // Mã khu
                    '1' => ['required', 'string', 'max:150'], // Tên khu
                    '2' => ['required', 'string', 'in:Phòng trọ,Căn hộ,Homestay,Nhà nguyên căn'],
                    '4' => ['required', 'string', 'max:255'], // Địa chỉ
                ],
                ['required' => 'Cột [:attribute] bắt buộc nhập.', 'in' => 'Giá trị [:attribute] không hợp lệ.'],
                ['0' => 'Mã khu nhà', '1' => 'Tên khu nhà', '2' => 'Loại nhà', '4' => 'Địa chỉ']
            );

            if ($validator->fails()) {
                $this->addError($actualRowNumber, $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($rowData) {
                    $typeMap = ['Phòng trọ' => 'boarding_house', 'Căn hộ' => 'apartment', 'Homestay' => 'homestay', 'Nhà nguyên căn' => 'house'];
                    $statusMap = ['Hoạt động' => 'active', 'Ngừng hoạt động' => 'inactive'];

                    $mappedData = [
                        'property_name'                 => $rowData[1],
                        'property_type'                 => $typeMap[trim((string)$rowData[2])] ?? 'boarding_house',
                        'property_status'               => $statusMap[trim((string)($rowData[3] ?? ''))] ?? 'active',
                        'property_address'              => $rowData[4],
                        'property_floors_count'         => $rowData[5] ?? 1,
                        'property_expected_rooms_count' => $rowData[6] ?? 0,
                        'property_manager_name'         => $rowData[7],
                        'property_description'          => $rowData[8],
                    ];

                    $this->propertyService->firstOrCreateFromImport($this->userId, strtoupper(trim((string)$rowData[0])), $mappedData);
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
        $this->errors[] = ['sheet' => 'Sheet 1 (Khu Nhà)', 'row' => $row, 'messages' => $messages];
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
