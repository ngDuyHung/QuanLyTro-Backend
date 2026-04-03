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
}
