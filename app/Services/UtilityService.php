<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\MeterReading;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UtilityService
{
    /**
     * Tạo chỉ số mới. Tự động truy xuất chỉ số cũ từ kỳ trước liền kề.
     */
    public function createReading(array $data): MeterReading
    {
        // 1. Tự động lấy chỉ số cũ (previous_reading) từ bản ghi mới nhất
        $lastReading = MeterReading::where('lease_id', $data['lease_id'])
            ->where('type', $data['type'])
            // XÓA dòng này ->where('reading_date', '<=', $data['reading_date'])
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->first();

        $previousReading = $lastReading ? $lastReading->current_reading : 0;

        // 2. Validate nghiệp vụ: Số mới không được nhỏ hơn số cũ
        if ($data['current_reading'] < $previousReading) {
            throw new BusinessException("Chỉ số mới ({$data['current_reading']}) không hợp lệ vì nhỏ hơn chỉ số cũ kỳ trước ({$previousReading}).");
        }

        $reading = new MeterReading([
            'lease_id' => $data['lease_id'],
            'type' => $data['type'],
            'previous_reading' => $previousReading,
            'current_reading' => $data['current_reading'],
            'reading_date' => $data['reading_date'],
            'note' => $data['note'] ?? null,
        ]);

        // 3. Xử lý lưu ảnh
        if (isset($data['meter_image']) && $data['meter_image'] instanceof UploadedFile) {
            $ext = $data['meter_image']->extension();
            $path = $data['meter_image']->storeAs(
                "utilities/lease_{$data['lease_id']}",
                "{$data['type']}_" . time() . ".{$ext}",
                'public'
            );
            $reading->meter_image = $path;
        }

        $reading->save();

        return $reading;
    }

    /**
     * Cập nhật chỉ số. Chặn cập nhật nếu đã xuất hóa đơn.
     */
    public function updateReading(MeterReading $reading, array $data): MeterReading
    {
        // Không cho sửa nếu đã được gắn vào một hóa đơn
        if ($reading->invoice_id) {
            throw new BusinessException('Không thể sửa chỉ số điện/nước đã được chốt vào hóa đơn.');
        }

        // Lấy số cũ mới truyền lên (nếu có), nếu không có thì giữ số cũ hiện tại
        $newPrevious = $data['previous_reading'] ?? $reading->previous_reading;

        // Kiểm tra số mới so với số cũ (đã cập nhật)
        if (isset($data['current_reading']) && $data['current_reading'] < $newPrevious) {
            throw new BusinessException("Chỉ số mới không được nhỏ hơn chỉ số cũ ({$newPrevious}).");
        }

        // Cập nhật các field cơ bản (Bao gồm cả số cũ)
        $reading->previous_reading = $newPrevious;
        $reading->current_reading = $data['current_reading'] ?? $reading->current_reading;
        $reading->reading_date = $data['reading_date'] ?? $reading->reading_date;

        // Xử lý ảnh
        if (isset($data['remove_image']) && $data['remove_image']) {
            if ($reading->meter_image) Storage::disk('public')->delete($reading->meter_image);
            $reading->meter_image = null;
        } elseif (isset($data['meter_image']) && $data['meter_image'] instanceof UploadedFile) {
            if ($reading->meter_image) Storage::disk('public')->delete($reading->meter_image);

            $ext = $data['meter_image']->extension();
            $reading->meter_image = $data['meter_image']->storeAs(
                "utilities/lease_{$reading->lease_id}",
                "{$reading->type->value}_" . time() . ".{$ext}",
                'public'
            );
        }

        $reading->save();

        return $reading;
    }

    /**
     * Xóa chỉ số.
     */
    public function deleteReading(MeterReading $reading): void
    {
        if ($reading->invoice_id) {
            throw new BusinessException('Không thể xóa chỉ số điện/nước đã được lập hóa đơn.');
        }

        if ($reading->meter_image) {
            Storage::disk('public')->delete($reading->meter_image);
        }

        $reading->delete();
    }

    /**
     * Phân tích chỉ số tiêu thụ 6 kỳ gần nhất của một phòng.
     * Tính trung bình động và trả về dữ liệu biểu đồ.
     */
    public function analyze6Months(int $roomId, string $type): array
    {
        // TỐI ƯU: Lấy danh sách ID các hợp đồng (bao gồm cũ và mới) của phòng này
        $leaseIds = Lease::where('room_id', $roomId)->pluck('id');

        // 1. Lấy 6 bản ghi chốt số gần nhất của phòng này bằng whereIn
        $readings = MeterReading::whereIn('lease_id', $leaseIds)
            ->where('type', $type)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        // Nếu phòng chưa có dữ liệu chốt số nào
        if ($readings->isEmpty()) {
            return [
                'summary' => [
                    'has_data' => false,
                    'message' => 'Phòng này chưa có dữ liệu chốt số.',
                ],
                'chart_data' => []
            ];
        }

        // 2. Đảo ngược mảng để sắp xếp theo thời gian tăng dần (Phục vụ vẽ Chart từ trái qua phải)
        $chronologicalReadings = $readings->reverse()->values();

        $chartData = [];
        $totalUsage = 0;
        $count = $chronologicalReadings->count();

        // 3. Xây dựng mảng Chart Data và tính Tổng tiêu thụ
        foreach ($chronologicalReadings as $reading) {
            $usage = max(0, $reading->current_reading - $reading->previous_reading);
            $totalUsage += $usage;

            $chartData[] = [
                'month' => \Carbon\Carbon::parse($reading->reading_date)->format('m/Y'),
                'usage' => $usage,
                'reading_date' => $reading->reading_date,
            ];
        }

        // 4. Tính toán Trung bình cộng và Độ lệch của tháng hiện tại
        $average = $count > 0 ? round($totalUsage / $count, 1) : 0;
        $currentUsage = $chartData[$count - 1]['usage']; // Tháng mới nhất

        $differenceValue = $currentUsage - $average;
        $differencePercent = $average > 0 ? round(($differenceValue / $average) * 100, 1) : 0;

        // 5. Đánh giá trạng thái (Business Logic)
        $status = 'normal';
        $message = 'Mức tiêu thụ bình thường, ổn định.';

        // Cảnh báo nếu biến động quá 15% so với mức trung bình
        if ($differencePercent >= 15) {
            $status = 'warning_high';
            $message = 'Tăng đột biến ' . abs($differencePercent) . '% so với mức trung bình.';
        } elseif ($differencePercent <= -15) {
            $status = 'warning_low';
            $message = 'Giảm ' . abs($differencePercent) . '% so với mức trung bình.';
        }

        return [
            'summary' => [
                'has_data' => true,
                'average_6_months' => $average,
                'current_usage' => $currentUsage,
                'difference_value' => round($differenceValue, 1),
                'difference_percent' => $differencePercent,
                'status' => $status,
                'message' => $message,
                'data_count' => $count,
            ],
            'chart_data' => $chartData
        ];
    }
}
