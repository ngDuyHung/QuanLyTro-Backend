<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetReportRequest extends FormRequest
{
    /**
     * Xác định xem người dùng có quyền thực hiện request này không.
     */
    public function authorize(): bool
    {
        return true; // Quyền đã được check qua middleware auth:sanctum
    }

    /**
     * Các rule validation áp dụng cho request.
     */
    public function rules(): array
    {
        return [
            // Kiểm tra property_id nếu được truyền lên phải tồn tại và thuộc về user đang đăng nhập
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
            ],

            // Bắt buộc phải có ngày bắt đầu và ngày kết thúc để giới hạn dữ liệu tính toán
            'from_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'to_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from_date', // Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu
            ],
        ];
    }

    /**
     * Tùy chỉnh thông báo lỗi (Tiếng Việt).
     */
    public function messages(): array
    {
        return [
            'property_id.integer' => 'Mã khu nhà phải là số.',
            'property_id.exists'  => 'Khu nhà không tồn tại hoặc bạn không có quyền truy cập.',

            'from_date.required'    => 'Vui lòng chọn ngày bắt đầu.',
            'from_date.date_format' => 'Ngày bắt đầu phải theo định dạng YYYY-MM-DD.',

            'to_date.required'      => 'Vui lòng chọn ngày kết thúc.',
            'to_date.date_format'   => 'Ngày kết thúc phải theo định dạng YYYY-MM-DD.',
            'to_date.after_or_equal' => 'Ngày kết thúc không được nhỏ hơn ngày bắt đầu.',
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước khi đưa vào validation (Nếu cần gán giá trị mặc định).
     */
    protected function prepareForValidation(): void
    {
        // Nếu client không gửi lên from_date / to_date, mặc định lấy từ đầu tháng đến hiện tại
        if (!$this->has('from_date') || !$this->has('to_date')) {
            $this->merge([
                'from_date' => $this->input('from_date', now()->startOfMonth()->toDateString()),
                'to_date'   => $this->input('to_date', now()->endOfDay()->toDateString()),
            ]);
        }
    }
}
