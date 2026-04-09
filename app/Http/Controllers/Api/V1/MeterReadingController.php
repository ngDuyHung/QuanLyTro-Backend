<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeterReading\StoreMeterReadingRequest;
use App\Http\Requests\MeterReading\UpdateMeterReadingRequest;
use App\Http\Resources\MeterReading\MeterReadingResources;
use App\Models\Lease;
use App\Models\MeterReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeterReadingController extends Controller
{
    // Lấy danh sách chỉ số tiêu thụ theo hợp đồng
    public function index(Request $request, int $leaseId): JsonResponse
    {
        $lease = Lease::whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($leaseId);

        $readings = $lease->meterReadings()
            ->orderBy('reading_date', 'desc')
            ->get();

        return MeterReadingResources::collection($readings)->response();
    }

    // Tạo mới chỉ số tiêu thụ (previous_reading tự động lấy từ bản ghi trước)
    public function store(StoreMeterReadingRequest $request, int $leaseId): JsonResponse
    {
        $lease = Lease::whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($leaseId);

        // Tự động lấy chỉ số cũ từ bản ghi cuối cùng cùng loại
        $lastReading = MeterReading::where('lease_id', $lease->id)
            ->where('type', $request->type)
            ->latest('reading_date')
            ->first();

        $previousReading = $lastReading ? $lastReading->current_reading : 0;

        // Kiểm tra chỉ số mới phải >= chỉ số cũ
        if ($request->current_reading < $previousReading) {
            throw new BusinessException(
                "Chỉ số mới ({$request->current_reading}) không được nhỏ hơn chỉ số cũ ({$previousReading})."
            );
        }

        $meterReading = MeterReading::create([
            'lease_id'         => $lease->id,
            'type'             => $request->type,
            'previous_reading' => $previousReading,
            'current_reading'  => $request->current_reading,
            'reading_date'     => $request->reading_date,
        ]);

        if ($request->hasFile('meter_image')) {
            $meterReading->meter_image = $request->file('meter_image')
                ->store("meter_readings/{$meterReading->id}", 'public');
            $meterReading->save();
        }

        return MeterReadingResources::make($meterReading)->response()->setStatusCode(201);
    }

    // Lấy chi tiết chỉ số tiêu thụ
    public function show(Request $request, int $id): JsonResponse
    {
        $meterReading = MeterReading::whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return MeterReadingResources::make($meterReading)->response();
    }

    // Cập nhật chỉ số tiêu thụ (chỉ khi chưa liên kết hóa đơn)
    public function update(UpdateMeterReadingRequest $request, int $id): JsonResponse
    {
        $meterReading = MeterReading::whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        if ($meterReading->invoice_id !== null) {
            throw new BusinessException('Không thể sửa chỉ số đã liên kết với hóa đơn.');
        }

        // Kiểm tra chỉ số mới phải >= chỉ số cũ
        $newCurrentReading = $request->current_reading ?? $meterReading->current_reading;
        if ($newCurrentReading < $meterReading->previous_reading) {
            throw new BusinessException(
                "Chỉ số mới ({$newCurrentReading}) không được nhỏ hơn chỉ số cũ ({$meterReading->previous_reading})."
            );
        }

        $meterReading->update($request->validated());

        if ($request->hasFile('meter_image')) {
            $meterReading->meter_image = $request->file('meter_image')
                ->store("meter_readings/{$meterReading->id}", 'public');
            $meterReading->save();
        }

        return MeterReadingResources::make($meterReading)->response();
    }

    // Xóa chỉ số tiêu thụ (chỉ khi chưa liên kết hóa đơn)
    public function destroy(Request $request, int $id): JsonResponse
    {
        $meterReading = MeterReading::whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        if ($meterReading->invoice_id !== null) {
            throw new BusinessException('Không thể xóa chỉ số đã liên kết với hóa đơn.');
        }

        $meterReading->delete();

        return response()->json(['message' => 'Xóa chỉ số tiêu thụ thành công.']);
    }
}
