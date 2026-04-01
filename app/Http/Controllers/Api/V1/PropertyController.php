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

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $properties = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->when($request->search, fn ($q) =>
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
        $property = Property::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $property->loadCount('rooms');

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    public function update(UpdatePropertyRequest $request, int $id): PropertyResource
    {
        $property = Property::where('user_id', $request->user()->id)->findOrFail($id);

        $property->update($request->validated());
        $property->loadCount('rooms');

        return new PropertyResource($property);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $property = Property::where('user_id', $request->user()->id)->findOrFail($id);

        if ($property->rooms()->exists()) {
            throw new BusinessException('Không thể xóa khu nhà vì vẫn còn phòng bên trong.');
        }

        $property->delete();

        return response()->json(['message' => 'Xóa khu nhà thành công.']);
    }
}
