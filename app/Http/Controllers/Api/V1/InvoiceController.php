<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\Domain\BusinessException;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\In;
use Symfony\Component\HttpFoundation\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Lấy danh sách hóa đơn của chủ trọ đang đăng nhập.
     *
     * Hỗ trợ filter:
     * - property_id: lọc theo khu nhà
     * - room_id: lọc theo phòng
     * - lease_id: lọc theo hợp đồng
     * - status: lọc theo trạng thái hóa đơn
     * - invoice_type: monthly/checkin/checkout/adjustment
     * - period_from, period_to: lọc theo kỳ hóa đơn
     */
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->with([
                'lease:id,room_id,tenant_id,start_date,end_date,billing_day,status',
                'lease.room:id,property_id,name',
                'lease.room.property:id,user_id,name,address',
                'room:id,property_id,name',
                'property:id,user_id,name',
                'items',
                'meterReadings',
                'allocations.financialTransaction',
            ])
            ->whereHas('lease.room.property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->when($request->query('property_id'), function ($query, $propertyId): void {
                $query->where('property_id', $propertyId);
            })
            ->when($request->query('room_id'), function ($query, $roomId): void {
                $query->where('room_id', $roomId);
            })
            ->when($request->query('lease_id'), function ($query, $leaseId): void {
                $query->where('lease_id', $leaseId);
            })
            ->when($request->query('status'), function ($query, $status): void {
                $query->where('status', $status);
            })
            ->when($request->query('invoice_type'), function ($query, $invoiceType): void {
                $query->where('invoice_type', $invoiceType);
            })
            ->when($request->query('period_from'), function ($query, $periodFrom): void {
                $query->whereDate('period_from', '>=', $periodFrom);
            })
            ->when($request->query('period_to'), function ($query, $periodTo): void {
                $query->whereDate('period_to', '<=', $periodTo);
            })
            ->when($request->query('search'), function ($query, $search): void {
                $query->where('invoice_code', 'like', '%' . $search . '%');
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return InvoiceResource::collection($invoices)->response();
    }

    /**
     * Tạo hóa đơn mới.
     *
     * Logic tính tổng tiền, sinh mã hóa đơn, tạo invoice_items
     * được xử lý trong InvoiceService.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->createInvoice(
            data: $request->validated(),
            userId: $request->user()->id
        );

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Tạo hóa đơn hàng loạt.
     *
     * Hiện tại mình để method này để route không bị lỗi.
     * Sau này mình sẽ viết riêng BulkCreateInvoiceRequest + service xử lý hàng loạt.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Chức năng tạo hóa đơn hàng loạt sẽ được xử lý ở bước sau.',
        ], 501);
    }

    /**
     * Xem chi tiết hóa đơn.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->findOwnedInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        $invoice->load([
            'lease.room.property',
            'lease.tenant',
            'property.user',
            'room',
            'items.servicePrice',
            'allocations.financialTransaction',
            'financialTransactions',
            'meterReadings',
        ]);

        return (new InvoiceResource($invoice))->response();
    }

    /**
     * Cập nhật thông tin nhẹ của hóa đơn.
     *
     * Chỉ cho sửa hóa đơn ở trạng thái draft.
     * Các dòng chi tiết tiền nên xử lý bằng service riêng nếu cần sửa sâu.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->findOwnedInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        if ($invoice->status !== 'draft') {
            throw new BusinessException('Chỉ có thể cập nhật hóa đơn đang ở trạng thái nháp.');
        }

        $data = $request->validate([
            'period_from' => ['sometimes', 'date'],
            'period_to' => ['sometimes', 'date', 'after:period_from'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'period_to.after' => 'Ngày kết thúc kỳ hóa đơn phải sau ngày bắt đầu.',
        ]);

        $invoice->update($data);

        return (new InvoiceResource($invoice->fresh([
            'lease.room.property',
            'items',
        ])))->response();
    }

    /**
     * Phát hành hóa đơn.
     *
     * Sau khi phát hành, hóa đơn chuyển từ draft sang issued.
     */
    public function issue(Request $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->findOwnedInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        $invoice = $this->invoiceService->issueInvoice(
            invoice: $invoice,
            userId: $request->user()->id
        );

        return (new InvoiceResource($invoice))->response();
    }

    /**
     * Hủy hóa đơn.
     *
     * Chỉ hủy được nếu chưa phát sinh thanh toán.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy hóa đơn.',
            'cancel_reason.max' => 'Lý do hủy hóa đơn không được vượt quá 255 ký tự.',
        ]);

        $invoice = $this->invoiceService->findOwnedInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        $invoice = $this->invoiceService->cancelInvoice(
            invoice: $invoice,
            reason: $data['cancel_reason'],
            userId: $request->user()->id
        );

        return (new InvoiceResource($invoice))->response();
    }

    /**
     * Xóa hóa đơn.
     *
     * Chỉ cho xóa hóa đơn nháp chưa có cấn tiền.
     * Với hóa đơn đã phát hành, nên dùng cancel thay vì xóa vật lý.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->findOwnedInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        if ($invoice->status !== 'draft') {
            throw new BusinessException('Chỉ có thể xóa hóa đơn đang ở trạng thái nháp.');
        }

        if ($invoice->allocations()->exists()) {
            throw new BusinessException('Không thể xóa hóa đơn đã có giao dịch cấn tiền.');
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->items()->delete();
            $invoice->delete();
        });

        return response()->json([
            'message' => 'Xóa hóa đơn thành công.',
        ]);
    }

    /**
     * TÍNH TOÁN TRƯỚC DỮ LIỆU HÓA ĐƠN (PREPARE)
     */
    public function prepare(\App\Http\Requests\Invoice\PrepareInvoiceRequest $request): JsonResponse
    {
        $data = $this->invoiceService->prepareInvoiceData(
            leaseId: (int) $request->lease_id,
            periodTo: $request->period_to,
            userId: $request->user()->id,
            isCheckout: $request->boolean('is_checkout')
        );

        return response()->json(['data' => $data]);
    }

    public function previewHtml(int $id, Request $request, SettingService $settingService)
    {
        $html = $settingService->compileInvoiceHtml($id, $request->user()->id);
        return response()->json(['html' => $html]);
    }

    /**
     * Xuất ảnh bản in PDF Hóa đơn (Trả về file PNG)
     */
    // public function exportInvoicePdfImage(Request $request, $id, SettingService $settingService)
    // {
    //     $userId = $request->user()->id;

    //     $base64Image = $settingService->generateInvoicePdfImage((int) $id, $userId);
    //     $imageCode = base64_decode($base64Image);

    //     return response($imageCode)
    //         ->header('Content-Type', 'image/png')
    //         ->header('Content-Disposition', 'attachment; filename="Hoa_don_ban_in_' . $id . '.png"');
    // }


    public function countActive(Request $request): JsonResponse
    {
        $count = Invoice::query()
            ->whereHas('lease.room.property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->whereIn('status', ['draft', 'issued'])
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }
}
