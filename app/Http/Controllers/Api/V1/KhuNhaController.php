<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\KhuNha\StoreKhuNhaRequest;
use App\Http\Requests\KhuNha\UpdateKhuNhaRequest;
use App\Http\Resources\KhuNha\KhuNhaResource;
use App\Models\KhuNha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KhuNhaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $khuNhas = KhuNha::where('user_id', $request->user()->id)
            ->withCount('phong')
            ->when($request->search, fn ($q) =>
                $q->where('ten_khu', 'like', "%{$request->search}%")
                  ->orWhere('dia_chi', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return KhuNhaResource::collection($khuNhas)->response();
    }

    public function show(Request $request, int $id): KhuNhaResource
    {
        $khuNha = KhuNha::where('user_id', $request->user()->id)
            ->withCount('phong')
            ->findOrFail($id);

        return new KhuNhaResource($khuNha);
    }

    public function store(StoreKhuNhaRequest $request): JsonResponse
    {
        $khuNha = KhuNha::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $khuNha->loadCount('phong');

        return (new KhuNhaResource($khuNha))->response()->setStatusCode(201);
    }

    public function update(UpdateKhuNhaRequest $request, int $id): KhuNhaResource
    {
        $khuNha = KhuNha::where('user_id', $request->user()->id)->findOrFail($id);

        $khuNha->update($request->validated());
        $khuNha->loadCount('phong');

        return new KhuNhaResource($khuNha);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $khuNha = KhuNha::where('user_id', $request->user()->id)->findOrFail($id);

        if ($khuNha->phong()->exists()) {
            throw new BusinessException('Không thể xóa khu nhà vì vẫn còn phòng bên trong.');
        }

        $khuNha->delete();

        return response()->json(['message' => 'Xóa khu nhà thành công.']);
    }
}
