<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleName = $this->getRoleNames()->first();

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'zalo_id'        => $this->zalo_id,
            'zalo_linked_at' => $this->zalo_linked_at ? $this->zalo_linked_at->format('Y-m-d H:i:s') : null,
            'role'       => $roleName,
            'role_label' => UserRole::tryFrom($roleName ?? '')?->label(),
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
