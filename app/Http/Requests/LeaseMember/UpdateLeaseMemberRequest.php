<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaseMember;

use App\Enums\MemberRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaseMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relationship'  => ['sometimes', Rule::enum(MemberRelationship::class)],
            'note'          => ['nullable', 'string', 'max:255'],
            'move_in_date'  => ['nullable', 'date', 'date_format:Y-m-d'],
            'move_out_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:move_in_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'relationship.enum'                    => 'Quan hệ không hợp lệ. Chấp nhận: spouse, child, parent, sibling, friend, other.',
            'note.max'                             => 'Ghi chú không được vượt quá 255 ký tự.',
            'move_in_date.date'                    => 'Ngày chuyển vào không hợp lệ.',
            'move_in_date.date_format'             => 'Ngày chuyển vào phải đúng định dạng YYYY-MM-DD.',
            'move_out_date.date'                   => 'Ngày rời phòng không hợp lệ.',
            'move_out_date.date_format'            => 'Ngày rời phòng phải đúng định dạng YYYY-MM-DD.',
            'move_out_date.after_or_equal'         => 'Ngày rời phòng phải sau hoặc bằng ngày chuyển vào.',
        ];
    }

    public function attributes(): array
    {
        return [
            'relationship'  => 'quan hệ',
            'note'          => 'ghi chú',
            'move_in_date'  => 'ngày chuyển vào',
            'move_out_date' => 'ngày rời phòng',
        ];
    }
}
