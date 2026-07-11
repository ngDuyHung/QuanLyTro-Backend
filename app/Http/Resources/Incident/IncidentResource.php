<?php

declare(strict_types=1);

namespace App\Http\Resources\Incident;

use App\Enums\Incident\IncidentImageType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'repair_cost' => $this->repair_cost,
            'payer' => $this->payer?->value,
            'payer_label' => $this->payer?->label(),

            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),

            // Relations
            'property' => $this->whenLoaded('property', fn() => [
                'id' => $this->property->id,
                'name' => $this->property->name,
            ]),

            'room' => $this->whenLoaded('room', fn() => [
                'id' => $this->room->id,
                'name' => $this->room->name,
            ]),

            'reporter' => $this->whenLoaded('reportedByTenant', fn() => [
                'id' => $this->reportedByTenant->id,
                'full_name' => $this->reportedByTenant->full_name,
                'phone' => $this->reportedByTenant->phone,
            ]),

            // Liên kết dòng tiền
            'financial_transaction_id' => $this->financial_transaction_id,
            'invoice_id' => $this->invoice_id,

            // Bóc tách logic hình ảnh trả về cho Frontend dễ xử lý
            'images' => $this->whenLoaded('images', function () {
                $allImages = $this->images;

                return [
                    'before_repair' => $allImages
                        ->where('type', IncidentImageType::BeforeRepair)
                        ->map(fn($img) => [
                            'id' => $img->id,
                            'url' => asset('storage/' . ltrim($img->image_path, '/')),
                        ])->values(),

                    'after_repair' => $allImages
                        ->where('type', IncidentImageType::AfterRepair)
                        ->map(fn($img) => [
                            'id' => $img->id,
                            'url' => asset('storage/' . ltrim($img->image_path, '/')),
                        ])->values(),
                ];
            }),
        ];
    }
}
