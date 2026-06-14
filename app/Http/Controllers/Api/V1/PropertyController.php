<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $properties = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->when(
                $request->search,
                fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('address', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PropertyResource::collection($properties)->response();
    }

    public function show(Request $request, int $id): PropertyResource
    {
        $property = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->findOrFail($id);

        return new PropertyResource($property);
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $storedPath = null;

        try {
            DB::beginTransaction();

            $data = $request->validated();

            unset($data['cover_image']);

            $data['user_id'] = $request->user()->id;

            $property = Property::create($data);

            if ($request->hasFile('cover_image')) {
                $storedPath = $request
                    ->file('cover_image')
                    ->store("properties/{$property->id}", 'public');

                $property->update([
                    'cover_image_path' => $storedPath,
                ]);
            }

            DB::commit();

            return (new PropertyResource($property->fresh()))
                ->response()
                ->setStatusCode(201);
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function update(UpdatePropertyRequest $request, int $id): PropertyResource
    {
        $property = Property::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $newStoredPath = null;
        $oldImagePath = $property->cover_image_path;

        try {
            DB::beginTransaction();

            $data = $request->validated();

            unset($data['cover_image']);

            if ($request->hasFile('cover_image')) {
                $newStoredPath = $request
                    ->file('cover_image')
                    ->store("properties/{$property->id}", 'public');

                $data['cover_image_path'] = $newStoredPath;
            }

            $property->update($data);

            DB::commit();

            if ($newStoredPath && $oldImagePath) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return new PropertyResource($property->fresh());
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($newStoredPath) {
                Storage::disk('public')->delete($newStoredPath);
            }

            throw $exception;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $property = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->findOrFail($id);

        if ($property->rooms_count > 0) {
            throw new BusinessException('Chỉ có thể xóa khu nhà khi chưa có phòng.');
        }

        $propertyId = $property->id;
        $coverImagePath = $property->cover_image_path;

        $property->delete();

        if ($coverImagePath) {
            Storage::disk('public')->deleteDirectory("properties/{$propertyId}");
        }

        return response()->json([
            'message' => 'Xóa khu nhà thành công.',
        ]);
    }
}
