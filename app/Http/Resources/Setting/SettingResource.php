<?php

declare(strict_types=1);

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Nếu Service trả về là một Object Model Setting thì lấy ->value, còn nếu là chuỗi thô thì lấy chính nó
        $value = is_object($this->resource) ? $this->resource->value : $this->resource;

        return [
            'template' => $value ?? '',
        ];
    }
}