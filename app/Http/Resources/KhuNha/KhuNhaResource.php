<?php

declare(strict_types=1);

namespace App\Http\Resources\KhuNha;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KhuNhaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'ten_khu'    => $this->ten_khu,
            'dia_chi'    => $this->dia_chi,
            'mo_ta'      => $this->mo_ta,
            'tong_phong' => $this->phong_count ?? 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
