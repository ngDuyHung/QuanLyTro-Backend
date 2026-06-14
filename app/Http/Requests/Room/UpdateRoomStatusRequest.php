<?php

declare(strict_types=1);

namespace App\Http\Requests\Room;

use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRoomStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Chỉ cho phép thủ công chuyển available <-> maintenance
            // occupied được set tự động khi tạo hợp đồng
            'status' => ['required', new Enum(RoomStatus::class), 'in:available,maintenance'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái phòng không được để trống.',
            'status.in'       => 'Chỉ được chuyển trạng thái sang "available" hoặc "maintenance".',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'trạng thái phòng',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('name')) {
            $merge['name'] = $this->name ? trim((string) $this->name) : null;
        }

        if ($this->has('max_occupants')) {
            $merge['max_occupants'] = $this->filled('max_occupants')
                ? (int) $this->max_occupants
                : 0;
        }

        if ($this->has('allow_shared')) {
            $merge['allow_shared'] = filter_var($this->allow_shared, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('is_public')) {
            $merge['is_public'] = filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}
