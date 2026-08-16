<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoomStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Room;
use App\Models\RoomPriceHistory;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RoomService
{
    private const MAX_IMAGES_PER_ROOM = 5;

    /**
     * Đồng bộ ảnh khi cập nhật phòng:
     * - Xóa ảnh cũ theo deleted_image_ids
     * - Thêm ảnh mới
     * - Đổi ảnh bìa nếu có cover_image_id hoặc cover_image_index
     * - Đảm bảo luôn có 1 ảnh bìa nếu phòng còn ảnh
     *
     * @param array<int, UploadedFile> $newImages
     * @param array<int, int|string> $deletedImageIds
     * @return array{stored_paths: array<int, string>, paths_to_delete_after_commit: array<int, string>}
     */
    public function syncImages(
        Room $room,
        array $newImages = [],
        array $deletedImageIds = [],
        ?int $coverImageId = null,
        ?int $coverImageIndex = null
    ): array {
        $storedPaths = [];
        $pathsToDeleteAfterCommit = [];

        try {
            $deletedImageIds = collect($deletedImageIds)
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | 1. Xóa record ảnh cũ, chưa xóa file vật lý ngay
            |--------------------------------------------------------------------------
            */
            if (!empty($deletedImageIds)) {
                $imagesToDelete = $room->images()
                    ->whereIn('id', $deletedImageIds)
                    ->get();

                foreach ($imagesToDelete as $image) {
                    if ($image->image_path) {
                        $pathsToDeleteAfterCommit[] = $image->image_path;
                    }

                    $image->delete();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Kiểm tra tổng số ảnh sau khi thêm mới
            |--------------------------------------------------------------------------
            */
            $remainingImagesCount = $room->images()->count();
            $newImagesCount = count($newImages);

            if (($remainingImagesCount + $newImagesCount) > self::MAX_IMAGES_PER_ROOM) {
                throw new BusinessException('Mỗi phòng chỉ được có tối đa 5 ảnh.');
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Thêm ảnh mới
            |--------------------------------------------------------------------------
            */
            $currentMaxSortOrder = (int) ($room->images()->max('sort_order') ?? -1);
            $createdImages = [];

            foreach ($newImages as $index => $image) {
                if (!$image instanceof UploadedFile) {
                    continue;
                }

                $path = $image->store("rooms/{$room->id}", 'public');

                $storedPaths[] = $path;

                $createdImage = $room->images()->create([
                    'image_path' => $path,
                    'is_cover' => false,
                    'sort_order' => $currentMaxSortOrder + $index + 1,
                ]);

                $createdImages[$index] = $createdImage;
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Đổi ảnh bìa nếu người dùng có chọn
            |--------------------------------------------------------------------------
            */
            $this->applyCoverImage(
                room: $room,
                createdImages: $createdImages,
                coverImageId: $coverImageId,
                coverImageIndex: $coverImageIndex
            );

            /*
            |--------------------------------------------------------------------------
            | 5. Nếu phòng còn ảnh nhưng chưa có ảnh bìa thì tự chọn ảnh đầu tiên
            |--------------------------------------------------------------------------
            */
            $this->ensureRoomHasCoverImage($room);

            return [
                'stored_paths' => $storedPaths,
                'paths_to_delete_after_commit' => $pathsToDeleteAfterCommit,
            ];
        } catch (Throwable $exception) {
            $this->deleteFiles($storedPaths);

            throw $exception;
        }
    }

    /**
     * Xóa toàn bộ record ảnh của phòng.
     * Chỉ trả về danh sách file cần xóa sau khi transaction commit.
     *
     * @return array<int, string>
     */
    public function deleteAllImageRecords(Room $room): array
    {
        $images = $room->images()->get();

        $pathsToDeleteAfterCommit = $images
            ->pluck('image_path')
            ->filter()
            ->values()
            ->all();

        $room->images()->delete();

        return $pathsToDeleteAfterCommit;
    }

    /**
     * @param array<int, string> $paths
     */
    public function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * @param array<int, mixed> $createdImages
     */
    private function applyCoverImage(
        Room $room,
        array $createdImages,
        ?int $coverImageId,
        ?int $coverImageIndex
    ): void {
        $coverWasRequested = $coverImageId !== null || $coverImageIndex !== null;

        if (!$coverWasRequested) {
            return;
        }

        $room->images()->update(['is_cover' => false]);

        if ($coverImageId !== null) {
            $coverImage = $room->images()
                ->where('id', $coverImageId)
                ->first();

            if ($coverImage) {
                $coverImage->update(['is_cover' => true]);
            }

            return;
        }

        if ($coverImageIndex !== null && isset($createdImages[$coverImageIndex])) {
            $createdImages[$coverImageIndex]->update(['is_cover' => true]);
        }
    }
    // Nếu sau tất cả thao tác mà phòng vẫn còn ảnh nhưng không có ảnh nào được đánh dấu là bìa thì tự động chọn ảnh đầu tiên làm ảnh bìa
    private function ensureRoomHasCoverImage(Room $room): void
    {
        $hasCover = $room->images()
            ->where('is_cover', true)
            ->exists();

        if ($hasCover) {
            return;
        }

        $firstImage = $room->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($firstImage) {
            $firstImage->update(['is_cover' => true]);
        }
    }


    public function createFromImport(int $propertyId, int $userId, array $data, bool $isFullScenario): Room
    {
        $roomName = trim((string)$data['room_name']);

        // Check trùng phòng
        $roomExists = Room::where('property_id', $propertyId)
            ->where('name', $roomName)
            ->exists();

        if ($roomExists) {
            throw new Exception("Phòng '{$roomName}' đã tồn tại trong khu nhà.");
        }

        $roomStatus = $isFullScenario ? RoomStatus::Occupied->value : (strtolower(trim((string)$data['room_status'])) ?: RoomStatus::Available->value);

        $room = Room::create([
            'property_id'   => $propertyId,
            'name'          => $roomName,
            'current_price' => (int)$data['room_current_price'],
            'deposit_amount' => !empty($data['room_deposit']) ? (int)$data['room_deposit'] : 0,
            'floor_number'  => $data['room_floor_number'] !== null ? (int)$data['room_floor_number'] : null,
            'status'        => $roomStatus,
            'area'          => $data['room_area'] ?? null,
            'max_occupants' => !empty($data['room_max_occupants']) ? (int)$data['room_max_occupants'] : 0,
            'billing_day'   => $data['room_billing_day'] ?? null,
            'allow_shared'  => !empty($data['room_allow_shared']),
            'is_public'     => !empty($data['room_is_public']),
            'description'   => $data['room_description'] ?? null,
            'sort_order'    => !empty($data['room_sort_order']) ? (int)$data['room_sort_order'] : 0,
        ]);

        // Ghi lịch sử giá nếu là kịch bản đầy đủ
        if ($isFullScenario && $room->current_price > 0) {
            RoomPriceHistory::create([
                'room_id'        => $room->id,
                'user_id'        => $userId,
                'old_price'      => 0,
                'new_price'      => $room->current_price,
                'effective_date' => $data['lease_start_date'],
                'note'           => 'Giá khởi tạo khi import hợp đồng.',
            ]);
        }

        return $room;
    }
}
