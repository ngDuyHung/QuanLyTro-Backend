<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\MeterType;
use App\Enums\RoomStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TenantService
{
    /**
     * Tạo khách thuê mới 
     
     */
    public function createTenant(array $tenantData): Tenant
    {
        $tenant = Tenant::create([
            'full_name' => $tenantData['full_name'],
            'email' => $tenantData['email'] ?? null,
            'phone' => $tenantData['phone'],
            'id_card_number' => $tenantData['id_card_number'],
        ]);

        // Logic xử lý file ảnh CCCD nếu có
        if (isset($tenantData['id_card_front_image']) && $tenantData['id_card_front_image'] instanceof UploadedFile) {
            $ext = $tenantData['id_card_front_image']->extension();
            $tenant->id_card_front_image = $tenantData['id_card_front_image']
                ->storeAs("tenants/{$tenant->id}", "id_card_front.{$ext}", 'public');
        }

        if (isset($tenantData['id_card_back_image']) && $tenantData['id_card_back_image'] instanceof UploadedFile) {
            $ext = $tenantData['id_card_back_image']->extension();
            $tenant->id_card_back_image = $tenantData['id_card_back_image']
                ->storeAs("tenants/{$tenant->id}", "id_card_back.{$ext}", 'public');
        }

        if ($tenant->isDirty()) {
            $tenant->save();
        }
        return $tenant;
    }
}
