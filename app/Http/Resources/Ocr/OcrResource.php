<?php

declare(strict_types=1);

namespace App\Http\Resources\Ocr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcrResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Chuyển mảng từ Service thành dạng object để xử lý bằng $this->
        $data = (object) $this->resource;

        return [
            'full_name' => $data->full_name ?? '',
            'id_card_number' => $data->id_card_number ?? '',
        ];
    }
}