<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Room;
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
                ->map(fn ($id) => (int) $id)
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
}