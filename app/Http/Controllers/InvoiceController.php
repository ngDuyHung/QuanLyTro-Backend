<?php

namespace App\Http\Controllers;

use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\In;
use Symfony\Component\HttpFoundation\JsonResponse;

class InvoiceController extends Controller
{
    // Lấy danh sách hóa đơn & lọc hóa đơn theo hợp đồng thuê (lease_id) và trạng thái (status) thuộc user đang đăng nhập
    public function index(Request $request): JsonResponse
    {
        $leaseId = $request->query('lease_id');
        $status = $request->query('status');

        // 1. BẮT BUỘC: Giới hạn hóa đơn chỉ thuộc về các tài sản do user hiện tại quản lý
        $invoices = Invoice::whereHas('lease.room.property', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        });

        // 2. Lọc theo lease_id (Vì lease_id nằm sẵn trên bảng invoices nên dùng where luôn cho nhanh)
        if ($leaseId) {
            $invoices->where('lease_id', $leaseId);
        }

        // 3. Lọc theo status (Nằm trên bảng invoices)
        if ($status) {
            $invoices->where('status', $status);
        }

        // Tùy chọn: Eager load relationships nếu FE cần hiển thị thông tin hợp đồng/phòng để tránh N+1 Query
        // $invoices->with(['lease.room']);

        // 4. Lấy kết quả 
        return InvoiceResource::collection($invoices->latest()->get())->response();
    }
}
