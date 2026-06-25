<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
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
        // 1. Tự động lấy chỉ số cũ (previous_reading) từ bản ghi mới nhất của hợp đồng này trước ngày chốt
        $lastReading = MeterReading::where('lease_id', $data['lease_id'])
            ->where('type', $data['type'])
            ->where('reading_date', '<=', $data['reading_date'])
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
        if ($reading->invoice_id) {
            throw new BusinessException('Không thể sửa chỉ số điện/nước đã được chốt vào hóa đơn.');
        }

        if (isset($data['current_reading']) && $data['current_reading'] < $reading->previous_reading) {
            throw new BusinessException("Chỉ số mới không được nhỏ hơn chỉ số cũ ({$reading->previous_reading}).");
        }

        // Cập nhật các field cơ bản
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
                "{$reading->type}_" . time() . ".{$ext}",
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
}