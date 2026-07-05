<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Services\PropertyService;
use App\Services\RoomService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class Sheet1PropertyRoomImport implements ToCollection
{
    private int $userId;
    private PropertyService $propertyService;
    private RoomService $roomService;

    // Biến lưu trữ báo cáo
    private int $successCount = 0;
    private int $failedCount = 0;
    private array $errors = [];

    // Cache để không truy vấn lại DB nhiều lần
    private array $propertyCache = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->propertyService = app(PropertyService::class);
        $this->roomService     = app(RoomService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // FIX 1: Bỏ qua 2 dòng đầu (Banner và Tiêu đề)
            if ($index < 2) continue;

            $actualRowNumber = $index + 1;

            // FIX 2: Đệm thêm phần tử null vào mảng để tránh lỗi Undefined array key
            // Sheet 1 có 18 cột, ta đệm dư thành 20 cột cho an toàn
            $rowData = array_pad($row->toArray(), 20, null);

            // Bỏ qua nếu dòng hoàn toàn trống
            if (empty($rowData[0]) && empty($rowData[9])) continue;

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
        $data = $this->translateExcelData($data);

        // 1. TẠO HOẶC LẤY KHU NHÀ
        $propertyCode = strtoupper(trim((string)$data[0]));
        if (!isset($this->propertyCache[$propertyCode])) {
            $mappedPropertyData = [
                'property_name'                 => $data[1],
                'property_type'                 => $data[2],
                'property_status'               => $data[3],
                'property_address'              => $data[4],
                'property_floors_count'         => $data[5],
                'property_expected_rooms_count' => $data[6],
                'property_manager_name'         => $data[7],
                'property_description'          => $data[8],
            ];
            $property = $this->propertyService->firstOrCreateFromImport($this->userId, $propertyCode, $mappedPropertyData);
            $this->propertyCache[$propertyCode] = $property->id;
        }

        $propertyId = $this->propertyCache[$propertyCode];

        // 2. TẠO PHÒNG
        $mappedRoomData = [
            'room_name'          => trim((string)$data[9]),
            'room_current_price' => $data[10],
            'room_floor_number'  => $data[11],
            'room_status'        => $data[12],
            'room_area'          => $data[13],
            'room_max_occupants' => $data[14],
            'room_billing_day'   => $data[15],
            'room_allow_shared'  => $data[16],
            'room_is_public'     => $data[17],
            'room_description'   => $data[18],
        ];

        // Tham số thứ 4 là false vì ở Sheet 1 chúng ta chưa biết phòng này có khách hay không
        $this->roomService->createFromImport($propertyId, $this->userId, $mappedRoomData, false);
    }

    private function translateExcelData(array $data): array
    {
        $propertyTypeMap = ['Phòng trọ' => 'boarding_house', 'Căn hộ' => 'apartment', 'Homestay' => 'homestay', 'Nhà nguyên căn' => 'house'];
        $propertyStatusMap = ['Hoạt động' => 'active', 'Ngừng hoạt động' => 'inactive'];
        $roomStatusMap = ['Còn trống' => 'available', 'Đang bảo trì' => 'maintenance', 'Đã cho thuê' => 'occupied'];
        $booleanMap = ['Có' => 1, 'Không' => 0];

        $data[2]  = $propertyTypeMap[trim((string)($data[2] ?? ''))] ?? $data[2];
        $data[3]  = $propertyStatusMap[trim((string)($data[3] ?? ''))] ?? $data[3];
        $data[12] = $roomStatusMap[trim((string)($data[12] ?? ''))] ?? $data[12];
        $data[16] = $booleanMap[trim((string)($data[16] ?? ''))] ?? 0;
        $data[17] = $booleanMap[trim((string)($data[17] ?? ''))] ?? 0;

        return $data;
    }

    private function addError(int $row, array $messages): void
    {
        $this->failedCount++;
        $this->errors[] = ['sheet' => 'Sheet 1 (Khu & Phòng)', 'row' => $row, 'messages' => $messages];
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
        $maxFloor = isset($data[5]) && is_numeric($data[5]) ? (int)$data[5] : 200;
        return [
            '0'  => ['required', 'string', 'max:50'],
            '1'  => ['required', 'string', 'max:150'],
            '2'  => ['required', 'string', 'in:Phòng trọ,Căn hộ,Homestay,Nhà nguyên căn'],
            '3'  => ['nullable', 'string', 'in:Hoạt động,Ngừng hoạt động'], // Không bắt buộc, default Hoạt động
            '4'  => ['required', 'string', 'max:255'],
            '5'  => ['nullable', 'integer', 'min:0', 'max:200'],
            '6'  => ['nullable', 'integer', 'min:0', 'max:1000'],
            '9'  => ['required', 'string', 'max:50'],
            '10' => ['required', 'integer', 'min:0'],
            '11' => ['nullable', 'integer', 'min:-1', "max:{$maxFloor}"],
            '12' => ['required', 'string', 'in:Còn trống,Đang bảo trì,Đã cho thuê'],
            '16' => ['required', 'string', 'in:Có,Không'],
            '17' => ['required', 'string', 'in:Có,Không'],
        ];
    }

    private function validationMessages(): array
    {
        return ['required' => 'Trường [:attribute] bắt buộc phải nhập dữ liệu.', 'integer' => 'Trường [:attribute] phải là số nguyên.'];
    }

    private function customAttributes(): array
    {
        return ['0' => 'Mã khu nhà', '1' => 'Tên khu nhà', '2' => 'Loại nhà', '4' => 'Địa chỉ', '9' => 'Tên/Số phòng', '10' => 'Giá thuê phòng', '12' => 'Trạng thái phòng', '16' => 'Cho ở ghép', '17' => 'Đăng công khai'];
    }
}
