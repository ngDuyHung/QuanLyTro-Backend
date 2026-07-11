<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Incident\IncidentImageType;
use App\Enums\Incident\IncidentPayer;
use App\Enums\Incident\IncidentStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IncidentService
{
    public function __construct(
        private readonly FinancialTransactionService $financialTransactionService
    ) {}

    /**
     * Tạo sự cố mới (Luôn bắt đầu ở trạng thái Pending)
     */
    public function createIncident(array $data, array $images, int $userId): Incident
    {
        $storedPaths = [];

        try {
            DB::beginTransaction();

            $data['status'] = IncidentStatus::Pending->value;
            $data['created_by'] = $userId;

            $incident = Incident::create($data);

            // Lưu ảnh lúc báo sự cố (before_repair)
            if (!empty($images)) {
                foreach ($images as $image) {
                    $path = $image->store("incidents/{$incident->id}", 'public');
                    $storedPaths[] = $path;

                    $incident->images()->create([
                        'image_path' => $path,
                        'type' => IncidentImageType::BeforeRepair->value,
                    ]);
                }
            }

            DB::commit();

            return $incident->fresh(['images', 'property', 'room']);
        } catch (Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($storedPaths);
            throw $e;
        }
    }

    /**
     * Cập nhật thông tin cơ bản của sự cố (Chỉ cho phép khi Pending)
     */
    public function updateIncident(Incident $incident, array $data): Incident
    {
        if ($incident->status !== IncidentStatus::Pending) {
            throw new BusinessException('Chỉ có thể cập nhật thông tin khi sự cố đang ở trạng thái Chờ tiếp nhận.');
        }

        $incident->update($data);

        return $incident->fresh(['images', 'property', 'room']);
    }

    /**
     * Chuyển trạng thái sang Đang xử lý (Processing)
     */
    public function processIncident(Incident $incident): Incident
    {
        if ($incident->status !== IncidentStatus::Pending) {
            throw new BusinessException('Sự cố phải ở trạng thái Chờ tiếp nhận mới có thể chuyển sang Đang xử lý.');
        }

        $incident->update([
            'status' => IncidentStatus::Processing->value,
        ]);

        return $incident;
    }

    /**
     * Chốt sự cố (Resolved) - Xử lý logic tài chính
     */
    public function resolveIncident(Incident $incident, array $data, array $images, int $userId): Incident
    {
        if ($incident->status !== IncidentStatus::Processing) {
            throw new BusinessException('Sự cố phải đang được xử lý mới có thể chốt hoàn thành.');
        }

        $storedPaths = [];

        try {
            DB::beginTransaction();

            $repairCost = (int) $data['repair_cost'];
            $payer = IncidentPayer::from($data['payer']);

            $updateData = [
                'status' => IncidentStatus::Resolved->value,
                'repair_cost' => $repairCost,
                'payer' => $payer->value,
                'resolved_at' => now(),
            ];

            // KỊCH BẢN 1: CHỦ TRỌ TRẢ TIỀN -> Tự động tạo phiếu chi
            if ($payer === IncidentPayer::Landlord && $repairCost > 0) {
                // Tận dụng FinancialTransactionService để tạo phiếu chi
                $transaction = $this->financialTransactionService->createGeneralTransaction(
                    data: [
                        'property_id' => $incident->property_id,
                        'room_id' => $incident->room_id,
                        'direction' => 'expense',
                        'category' => 'repair', // Category cho phí sửa chữa
                        'accounting_type' => 'expense',
                        'amount' => $repairCost,
                        'method' => 'cash', // Mặc định tiền mặt, admin có thể sửa lại sau
                        'transaction_date' => now()->toDateTimeString(),
                        'description' => "Chi phí sửa chữa sự cố: {$incident->title}",
                    ],
                    userId: $userId
                );

                $updateData['financial_transaction_id'] = $transaction->id;
            }
            // KỊCH BẢN 2: KHÁCH THUÊ ĐỀN -> Để trống invoice_id, InvoiceService sẽ tự động nhặt vào cuối tháng

            $incident->update($updateData);

            // Lưu ảnh nghiệm thu (after_repair)
            if (!empty($images)) {
                foreach ($images as $image) {
                    $path = $image->store("incidents/{$incident->id}/resolved", 'public');
                    $storedPaths[] = $path;

                    $incident->images()->create([
                        'image_path' => $path,
                        'type' => IncidentImageType::AfterRepair->value,
                    ]);
                }
            }

            DB::commit();

            return $incident->fresh(['images', 'financialTransaction', 'invoice']);
        } catch (Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($storedPaths);
            throw $e;
        }
    }

    /**
     * Hủy sự cố (Chỉ cho phép khi chưa giải quyết xong)
     */
    public function cancelIncident(Incident $incident): Incident
    {
        if (in_array($incident->status, [IncidentStatus::Resolved, IncidentStatus::Cancelled])) {
            throw new BusinessException('Sự cố này đã đóng, không thể hủy.');
        }

        $incident->update([
            'status' => IncidentStatus::Cancelled->value,
        ]);

        return $incident;
    }

    /**
     * Xóa vật lý sự cố (Chỉ cho xóa khi Pending hoặc Cancelled)
     */
    public function deleteIncident(Incident $incident): void
    {
        if (in_array($incident->status, [IncidentStatus::Processing, IncidentStatus::Resolved])) {
            throw new BusinessException('Không thể xóa sự cố đang xử lý hoặc đã có phát sinh chi phí.');
        }

        $imagePaths = $incident->images()->pluck('image_path')->toArray();

        DB::transaction(function () use ($incident) {
            $incident->images()->delete();
            $incident->delete();
        });

        // Xóa file ảnh trên storage sau khi DB xóa thành công
        $this->deleteFiles($imagePaths);
        Storage::disk('public')->deleteDirectory("incidents/{$incident->id}");
    }

    /**
     * Xóa danh sách file (Helper)
     */
    private function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
