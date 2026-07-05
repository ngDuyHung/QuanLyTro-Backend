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

class Sheet2ServiceImport implements ToCollection
{
    private int $userId;
    private int $successCount = 0;
    private int $failedCount = 0;
    private array $errors = [];
    private array $propertyCache = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Bỏ qua 2 dòng đầu
            if ($index < 2) continue;

            $actualRowNumber = $index + 1;

            // Đệm mảng lên 10 phần tử cho an toàn
            $rowData = array_pad($row->toArray(), 10, null);

            if (empty($rowData[0]) || empty($rowData[1])) continue;

            $validator = Validator::make(
                $rowData,
                $this->validationRules(),
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

        // Caching Property ID
        if (!isset($this->propertyCache[$propertyCode])) {
            $property = Property::where('user_id', $this->userId)->where('code', $propertyCode)->first();
            if (!$property) throw new \Exception("Không tìm thấy khu nhà có mã '{$propertyCode}'.");
            $this->propertyCache[$propertyCode] = $property->id;
        }
        $propertyId = $this->propertyCache[$propertyCode];

        // Dịch dữ liệu Excel
        $serviceMap = ['Điện' => 'electricity', 'Nước' => 'water', 'Rác' => 'garbage', 'Internet' => 'internet'];
        $freeTypeMap = ['Không' => 'none', 'Theo phòng' => 'per_room', 'Theo người' => 'per_person'];

        $serviceType = $serviceMap[trim((string)$data[1])] ?? null;
        if (!$serviceType) throw new \Exception("Loại dịch vụ không hợp lệ.");

        $unitPrice = (int)$data[2];
        $freeUnitType = $freeTypeMap[trim((string)($data[3] ?? 'Không'))] ?? 'none';
        $freeUnits = (int)($data[4] ?? 0);

        // Tạo hoặc Cập nhật giá dịch vụ cho khu nhà
        ServicePrice::updateOrCreate(
            ['property_id' => $propertyId, 'service_type' => $serviceType],
            [
                'unit_price'     => $unitPrice,
                'free_unit_type' => $freeUnitType,
                'free_units'     => $freeUnits,
                'effective_date' => now()->toDateString(), // Bắt đầu áp dụng từ hôm nay
            ]
        );
    }

    private function addError(int $row, array $messages): void
    {
        $this->failedCount++;
        $this->errors[] = ['sheet' => 'Sheet 2 (Dịch vụ)', 'row' => $row, 'messages' => $messages];
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

    private function validationRules(): array
    {
        return [
            '0' => ['required', 'string'],
            '1' => ['required', 'string', 'in:Điện,Nước,Rác,Internet'],
            '2' => ['required', 'integer', 'min:0'],
            '3' => ['nullable', 'string', 'in:Không,Theo phòng,Theo người'],
            '4' => ['nullable', 'integer', 'min:0'],
        ];
    }
    private function validationMessages(): array
    {
        return ['required' => 'Trường [:attribute] bắt buộc nhập.', 'integer' => 'Trường [:attribute] phải là số nguyên.'];
    }
    private function customAttributes(): array
    {
        return ['0' => 'Mã khu nhà', '1' => 'Loại dịch vụ', '2' => 'Đơn giá', '3' => 'Loại miễn phí', '4' => 'Số lượng miễn phí'];
    }
}
