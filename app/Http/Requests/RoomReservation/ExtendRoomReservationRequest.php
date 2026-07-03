<?php

declare(strict_types=1);

namespace App\Http\Requests\RoomReservation;

use Illuminate\Foundation\Http\FormRequest;

class ExtendRoomReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_move_in_date' => ['required', 'date', 'after:today'],
        ];
    }
}