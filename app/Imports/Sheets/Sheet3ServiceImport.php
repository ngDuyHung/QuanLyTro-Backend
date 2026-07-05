<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Models\Property;
use App\Models\ServicePrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet3ServiceImport implements ToCollection
{
    private int $userId;
    private int $successCount = 0, $failedCount = 0;
    private array $errors = [], $propertyCache = [];

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
                    '1' => ['required', 'string', 'in:Điện,Nước,Rác,Internet'],
                    '2' => ['required', 'integer', 'min:0'],
                ],
                ['required' => '[:attribute] bắt buộc nhập.', 'integer' => '[:attribute] phải là số nguyên.'],
                ['0' => 'Mã khu nhà', '1' => 'Loại dịch vụ', '2' => 'Đơn giá']
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
                        if (!$property) throw new \Exception("Không tìm thấy Mã khu '{$propertyCode}'.");
                        $this->propertyCache[$propertyCode] = $property->id;
                    }

                    $serviceMap = ['Điện' => 'electricity', 'Nước' => 'water', 'Rác' => 'garbage', 'Internet' => 'internet'];
                    $freeTypeMap = ['Không' => 'none', 'Theo phòng' => 'per_room', 'Theo người' => 'per_person'];

                    ServicePrice::updateOrCreate(
                        ['property_id' => $this->propertyCache[$propertyCode], 'service_type' => $serviceMap[trim((string)$rowData[1])]],
                        [
                            'unit_price'     => (int)$rowData[2],
                            'free_unit_type' => $freeTypeMap[trim((string)($rowData[3] ?? 'Không'))] ?? 'none',
                            'free_units'     => (int)($rowData[4] ?? 0),
                            'effective_date' => now()->toDateString(),
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
        $this->errors[] = ['sheet' => 'Sheet 3 (Giá Dịch Vụ)', 'row' => $row, 'messages' => $messages];
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
