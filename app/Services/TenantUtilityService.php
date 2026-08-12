<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\MeterReading;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TenantUtilityService
{
    public function __construct(
        private readonly UtilityService $utilityService
    ) {}

    /**
     * Tự động lấy hợp đồng đang hoạt động của khách thuê
     */
    private function getActiveLease(int $userId): Lease
    {
        $lease = Lease::with(['room:id,name,property_id', 'room.property:id,name'])
            ->whereHas('tenant', fn($q) => $q->where('user_id', $userId))
            ->where('status', 'active')
            ->first();

        if (!$lease) {
            throw new BusinessException('Bạn chưa có hợp đồng thuê phòng nào đang hoạt động.');
        }

        return $lease;
    }

    /**
     * Lấy danh sách lịch sử chốt số của phòng đang ở
     */
    public function getTenantReadings(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $lease = $this->getActiveLease($userId);

        $query = MeterReading::with(['lease.room.property'])
            ->where('lease_id', $lease->id);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['month'])) {
            $year = substr($filters['month'], 0, 4);
            $month = substr($filters['month'], 5, 2);
            $query->whereMonth('reading_date', $month)->whereYear('reading_date', $year);
        }

        return $query->latest('reading_date')->latest('id')->paginate($perPage);
    }

    /**
     * Lấy chỉ số cũ gần nhất để hiển thị ra UI cho khách dễ nhập
     */
    public function getCurrentReadings(int $userId): array
    {
        $lease = $this->getActiveLease($userId);

        $elec = MeterReading::where('lease_id', $lease->id)->where('type', 'electricity')->orderByDesc('reading_date')->orderByDesc('id')->first();
        $water = MeterReading::where('lease_id', $lease->id)->where('type', 'water')->orderByDesc('reading_date')->orderByDesc('id')->first();

        return [
            'room_name' => $lease->room->name ?? '',
            'property_name' => $lease->room->property->name ?? '',
            'electricity_previous' => $elec ? (int)$elec->current_reading : 0,
            'water_previous' => $water ? (int)$water->current_reading : 0,
        ];
    }

    /**
     * Submit gộp cả Điện và Nước vào chung 1 lần bấm
     */
    public function submitBatch(int $userId, array $data, ?UploadedFile $elecImage, ?UploadedFile $waterImage): void
    {
        $lease = $this->getActiveLease($userId);

        $readingDate = $data['reading_date'];
        $month = substr($readingDate, 0, 7); // Format: YYYY-MM

        // Check xem trong tháng này khách đã tự chốt lần nào chưa
        $existing = MeterReading::where('lease_id', $lease->id)
            ->where('reading_date', 'like', $month . '%')
            ->exists();

        if ($existing) {
            throw new BusinessException('Phòng của bạn đã được chốt chỉ số trong kỳ này. Nếu có sai sót, vui lòng báo lại cho quản lý.');
        }

        DB::transaction(function () use ($lease, $data, $elecImage, $waterImage) {
            // 1. Chốt Điện
            if (isset($data['electricity_reading']) && $data['electricity_reading'] !== '') {
                $this->utilityService->createReading([
                    'lease_id' => $lease->id,
                    'type' => 'electricity',
                    'current_reading' => (int) $data['electricity_reading'],
                    'reading_date' => $data['reading_date'],
                    'note' => $data['note'] ?? null,
                    'meter_image' => $elecImage
                ]);
            }

            // 2. Chốt Nước
            if (isset($data['water_reading']) && $data['water_reading'] !== '') {
                $this->utilityService->createReading([
                    'lease_id' => $lease->id,
                    'type' => 'water',
                    'current_reading' => (int) $data['water_reading'],
                    'reading_date' => $data['reading_date'],
                    'note' => $data['note'] ?? null,
                    'meter_image' => $waterImage
                ]);
            }
        });
    }

    /**
     * Xem chi tiết 1 bản ghi
     */
    public function getReadingDetail(int $id, int $userId): MeterReading
    {
        $lease = $this->getActiveLease($userId);

        return MeterReading::with(['lease.room.property'])
            ->where('lease_id', $lease->id)
            ->findOrFail($id);
    }
}
