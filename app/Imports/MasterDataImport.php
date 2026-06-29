<?php

declare(strict_types=1);

namespace App\Imports;

use App\Services\LeaseService;
use App\Services\PropertyService;
use App\Services\RoomService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Throwable;

class MasterDataImport implements ToCollection
{
    private int $userId;
    private array $propertyCache = [];
    private array $roomLeaseCache = [];
    private array $report = [
        'success_count' => 0,
        'failed_count'  => 0,
        'errors'        => [],
    ];

    private PropertyService $propertyService;
    private RoomService $roomService;
    private LeaseService $leaseService;

    public function __construct(int $userId)
    {
        $this->userId          = $userId;
        $this->propertyService = app(PropertyService::class);
        $this->roomService     = app(RoomService::class);
        $this->leaseService    = app(LeaseService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            $actualRowNumber = $index + 1;

            if (empty($row[0]) && empty($row[9])) {
                continue;
            }

            $rowData = $row->toArray();

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

                $this->report['success_count']++;
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();

                if (str_contains($errorMessage, 'is not a valid backing value for enum')) {
                    $errorMessage = "Lỗi dữ liệu: Loại nhà hoặc Trạng thái không khớp với cấu hình hệ thống.";
                } elseif (str_contains($errorMessage, 'Data too long')) {
                    $errorMessage = "Lỗi dữ liệu: Nội dung nhập vào quá dài so với giới hạn cho phép.";
                } elseif (str_contains($errorMessage, 'Integrity constraint violation')) {
                    $errorMessage = "Lỗi cơ sở dữ liệu: Dữ liệu bị trùng lặp hoặc vi phạm ràng buộc khóa.";
                } elseif (str_contains($errorMessage, 'Column not found')) {
                    $errorMessage = "Lỗi hệ thống: Cấu trúc cơ sở dữ liệu không khớp với cấu hình dữ liệu import.";
                }

                $this->addError($actualRowNumber, [$errorMessage]);
            }
        }
    }

    private function processRow(array $data): void
    {
        $data = $this->translateExcelData($data);
        $isFullScenario = !empty($data[19]); // Kiểm tra nếu có điền tên khách thuê
        $tenantRole = $data[23] ?? null;

        // 1. Xử lý Khu nhà (Cột 0)
        $propertyCode = strtoupper(trim((string)$data[0]));
        if (!isset($this->propertyCache[$propertyCode])) {
            $mappedPropertyData = [
                'property_type'                 => $data[2],
                'property_name'                 => $data[1],
                'property_status'               => $data[3],
                'property_floors_count'         => $data[5],
                'property_expected_rooms_count' => $data[6],
                'property_manager_name'         => $data[7],
                'property_address'              => $data[4],
                'property_description'          => $data[8],
            ];
            $property = $this->propertyService->firstOrCreateFromImport($this->userId, $propertyCode, $mappedPropertyData);
            $this->propertyCache[$propertyCode] = $property->id;
        }
        $propertyId = $this->propertyCache[$propertyCode];

        $roomName = trim((string)$data[9]);
        $roomCacheKey = "{$propertyId}_{$roomName}";

        // 2. PHÂN NHÁNH LOGIC DỰA TRÊN VAI TRÒ ĐƯỢC CHỌN TỪ EXCEL
        if ($isFullScenario && $tenantRole === 'member') {
            // TRƯỜNG HỢP: NGƯỜI Ở GHÉP
            $roomId = null;
            $leaseId = null;

            // Kiểm tra trong lượt import hiện tại xem phòng/hợp đồng đã được tạo từ dòng trên chưa
            if (isset($this->roomLeaseCache[$roomCacheKey])) {
                $roomId = $this->roomLeaseCache[$roomCacheKey]['room_id'];
                $leaseId = $this->roomLeaseCache[$roomCacheKey]['lease_id'];
            } else {
                // Nếu không có trong cache (Người đại diện đã có sẵn trong DB từ trước), tiến hành tra cứu DB
                $room = \App\Models\Room::where('property_id', $propertyId)->where('name', $roomName)->first();
                if (!$room) {
                    throw new \Exception("Không thể thêm người ở ghép vì phòng '{$roomName}' chưa tồn tại trong hệ thống.");
                }
                $activeLease = \App\Models\Lease::where('room_id', $room->id)->where('status', 'active')->first();
                if (!$activeLease) {
                    throw new \Exception("Không thể thêm người ở ghép vì phòng '{$roomName}' hiện không có hợp đồng nào đang hoạt động.");
                }
                $roomId = $room->id;
                $leaseId = $activeLease->id;
            }

            $mappedRoommateData = [
                'tenant_id_card_number' => $data[21],
                'tenant_full_name'      => $data[19],
                'tenant_phone'          => $data[20],
                'tenant_email'          => $data[22],
                'lease_start_date'      => $data[24],
            ];
            $this->leaseService->addRoommateFromImport($roomId, $leaseId, $mappedRoommateData);
        } else {
            // TRƯỜNG HỢP: KHỞI TẠO PHÒNG MỚI HOẶC NGƯỜI ĐẠI DIỆN HỢP ĐỒNG
            $mappedRoomData = [
                'room_name'          => $roomName,
                'room_current_price' => $data[10],
                'room_floor_number'  => $data[11],
                'room_status'        => $data[12],
                'room_area'          => $data[13],
                'room_max_occupants' => $data[14],
                'room_billing_day'   => $data[15],
                'room_allow_shared'  => $data[16],
                'room_is_public'     => $data[17],
                'room_description'   => $data[18],
                'lease_start_date'   => $data[24] ?? null,
            ];

            $room = $this->roomService->createFromImport($propertyId, $this->userId, $mappedRoomData, $isFullScenario);

            if ($isFullScenario && $tenantRole === 'representative') {
                $mappedLeaseData = [
                    'tenant_id_card_number'     => $data[21],
                    'tenant_full_name'          => $data[19],
                    'tenant_phone'              => $data[20],
                    'tenant_email'              => $data[22],
                    'lease_start_date'          => $data[24],
                    'lease_billing_day'         => $data[25],
                    'lease_room_price'          => $data[26], // Khớp: Giá chốt HĐ
                    'lease_deposit'             => $data[27], // SỬA: Lấy từ Index 27 (Tiền đặt cọc HĐ)
                    'lease_electricity_reading' => $data[28], // SỬA: Lấy từ Index 28 (Chỉ số ĐIỆN đầu)
                    'lease_water_reading'       => $data[29], // SỬA: Lấy từ Index 29 (Chỉ số NƯỚC đầu)
                    // 'lease_contract_number'  => $data[29], // XÓA: Vì file Excel mẫu không có cột Số hợp đồng
                    'room_current_price'        => $data[10],
                ];

                $lease = $this->leaseService->createFromImport($room->id, $mappedLeaseData);

                // Ghi nhận vào Cache để phục vụ cho các dòng ở ghép phía dưới
                $this->roomLeaseCache[$roomCacheKey] = [
                    'room_id'  => $room->id,
                    'lease_id' => $lease->id
                ];
            }
        }
    }

    private function translateExcelData(array $data): array
    {
        $propertyTypeMap = [
            'Phòng trọ'      => 'boarding_house',
            'Căn hộ'         => 'apartment',
            'Homestay'       => 'homestay',
            'Nhà nguyên căn' => 'house',
        ];

        $propertyStatusMap = [
            'Hoạt động'       => 'active',
            'Ngừng hoạt động' => 'inactive',
        ];

        $roomStatusMap = [
            'Còn trống'    => 'available',
            'Đang bảo trì' => 'maintenance',
            'Đã cho thuê'  => 'occupied',
        ];

        $booleanMap = [
            'Có'    => 1,
            'Không' => 0,
        ];

        $roleMap = [
            'Đại diện' => 'representative',
            'Ở ghép'   => 'member'
        ];

        $data[2]  = $propertyTypeMap[trim((string)($data[2] ?? ''))] ?? $data[2];
        $data[3]  = $propertyStatusMap[trim((string)($data[3] ?? ''))] ?? $data[3];
        $data[12] = $roomStatusMap[trim((string)($data[12] ?? ''))] ?? $data[12];
        $data[16] = $booleanMap[trim((string)($data[16] ?? ''))] ?? 0;
        $data[17] = $booleanMap[trim((string)($data[17] ?? ''))] ?? 0;
        $data[23] = $roleMap[trim((string)($data[23] ?? ''))] ?? $data[23];

        return $data;
    }

    public function getReport(): array
    {
        return $this->report;
    }

    private function addError(int $row, array $messages): void
    {
        $this->report['failed_count']++;
        $this->report['errors'][] = [
            'row'      => $row,
            'messages' => $messages,
        ];
    }

    private function validationRules(array $data): array
    {
        $maxFloor = isset($data[5]) && is_numeric($data[5]) ? (int)$data[5] : 200;

        return [
            '0'  => ['required', 'string', 'max:50'],
            '1'  => ['required', 'string', 'max:150'],
            '2'  => ['required', 'string', 'in:Phòng trọ,Căn hộ,Homestay,Nhà nguyên căn'],
            '3'  => ['required', 'string', 'in:Hoạt động,Ngừng hoạt động'],
            '4'  => ['required', 'string', 'max:255'],
            '5'  => ['required', 'integer', 'min:0', 'max:200'],
            '6'  => ['required', 'integer', 'min:0', 'max:1000'],
            '9'  => ['required', 'string', 'max:50'],
            '10' => ['required', 'integer', 'min:0'],
            '11' => ['nullable', 'integer', 'min:-1', "max:{$maxFloor}"],
            '12' => ['required', 'string', 'in:Còn trống,Đang bảo trì,Đã cho thuê'],
            '16' => ['required', 'string', 'in:Có,Không'],
            '17' => ['required', 'string', 'in:Có,Không'],

            '23' => ['required_with:19', 'nullable', 'string', 'in:Đại diện,Ở ghép'],
            '24' => ['required_with:19', 'nullable', 'date'],
            '20' => ['required_with:19', 'nullable', 'string', 'regex:/^[0-9]{9,15}$/'],
            '21' => ['required_with:19', 'nullable', 'string', 'max:20'],

            // Bắt buộc nhập Giá chốt HĐ, Điện, Nước nếu là Đại diện
            '26' => ['required_if:23,Đại diện', 'nullable', 'integer', 'min:0'], // Giá chốt HĐ
            '27' => ['nullable', 'integer', 'min:0'],                            // Tiền đặt cọc HĐ (Không bắt buộc)
            '28' => ['required_if:23,Đại diện', 'nullable', 'integer', 'min:0'], // Số điện
            '29' => ['required_if:23,Đại diện', 'nullable', 'integer', 'min:0'], // Số nước
        ];
    }

    private function validationMessages(): array
    {
        return [
            'required'      => 'Trường [:attribute] bắt buộc phải nhập dữ liệu.',
            'required_with' => 'Trường [:attribute] không được để trống khi phòng đã có Khách thuê.',
            'required_if'   => 'Trường [:attribute] bắt buộc phải khởi tạo khi khai báo khách thuê Đại diện.',
            'integer'       => 'Trường [:attribute] phải nhập định dạng số nguyên.',
            'date'          => 'Trường [:attribute] phải đúng định dạng ngày tháng (YYYY-MM-DD).',
            'in'            => 'Giá trị chọn cho trường [:attribute] không hợp lệ, vui lòng chọn từ danh sách có sẵn.',
            'regex'         => 'Định dạng dữ liệu của [:attribute] không đúng quy định.',
            '11.max'        => 'Tầng số của phòng không được vượt quá tổng số tầng của khu nhà đã khai báo (:max tầng).',
        ];
    }

    private function customAttributes(): array
    {
        return [
            '0'  => 'Mã khu nhà',
            '1'  => 'Tên khu nhà',
            '2'  => 'Loại nhà',
            '3'  => 'Trạng thái nhà',
            '4'  => 'Địa chỉ chi tiết',
            '5'  => 'Số tầng',
            '6'  => 'Số phòng dự kiến',
            '9'  => 'Tên/Số phòng',
            '10' => 'Giá thuê phòng',
            '11' => 'Tầng số',
            '12' => 'Trạng thái phòng',
            '16' => 'Cho ở ghép?',
            '17' => 'Đăng công khai?',
            '19' => 'Họ tên khách thuê',
            '20' => 'Số điện thoại khách',
            '21' => 'Số CCCD/CMND khách',
            '23' => 'Vai trò trong phòng',
            '24' => 'Ngày bắt đầu HĐ',
            '26' => 'Giá chốt HĐ',
            '27' => 'Tiền đặt cọc HĐ',   // THÊM MỚI
            '28' => 'Chỉ số ĐIỆN đầu',   // CHỈNH LẠI INDEX
            '29' => 'Chỉ số NƯỚC đầu',   // CHỈNH LẠI INDEX
        ];
    }
}
