<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'content'     => ['required', 'string'],

            // Bắt cứng các giá trị được phép truyền lên
            'type'        => ['required', Rule::in(['info', 'warning', 'billing'])],
            'target_type' => ['required', Rule::in(['all', 'property', 'room'])],

            'target_id'   => [
                Rule::requiredIf(fn() => in_array($this->target_type, ['property', 'room'])),
                'nullable',
                'integer'
            ],
            'action_url' => ['nullable', 'string', 'max:255'],
            'is_pinned'   => ['nullable', 'boolean'],
            'status'      => ['required', Rule::in(['draft', 'published'])],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'Vui lòng nhập tiêu đề thông báo.',
            'title.string'         => 'Tiêu đề thông báo phải là một chuỗi.',
            'title.max'            => 'Tiêu đề thông báo không được vượt quá 255 ký tự.',

            'content.required'     => 'Vui lòng nhập nội dung thông báo.',
            'content.string'       => 'Nội dung thông báo phải là một chuỗi.',

            'type.required'        => 'Vui lòng chọn loại thông báo.',
            'type.in'              => 'Loại thông báo không hợp lệ. Chỉ chấp nhận: info, warning, billing.',

            'target_type.required' => 'Vui lòng chọn loại đối tượng mục tiêu.',
            'target_type.in'       => 'Loại đối tượng mục tiêu không hợp lệ. Chỉ chấp nhận: all, property, room.',

            'target_id.required_if' => 'Vui lòng cung cấp ID của property hoặc room khi loại đối tượng mục tiêu là property hoặc room.',
            'target_id.integer'    => 'ID của property hoặc room phải là một số nguyên.',

            'is_pinned.boolean'    => 'Cờ ghim phải là giá trị boolean.',

            'status.required'      => 'Vui lòng chọn trạng thái thông báo.',
            'status.in'            => 'Trạng thái thông báo không hợp lệ. Chỉ chấp nhận: draft, published.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title'       => 'tiêu đề thông báo',
            'content'     => 'nội dung thông báo',
            'type'        => 'loại thông báo',
            'target_type' => 'loại đối tượng mục tiêu',
            'target_id'   => 'ID của property hoặc room',
            'is_pinned'   => 'cờ ghim',
            'status'      => 'trạng thái thông báo',
        ];
    }
}
