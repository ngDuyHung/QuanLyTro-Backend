<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\LockLedgerRequest;
use App\Http\Requests\Accounting\PreviewLedgerRequest;
use App\Http\Requests\Accounting\ShowAccountingLedgerRequest;
use App\Http\Resources\Accounting\AccountingLedgerResource;
use App\Models\AccountingLedger;
use App\Services\AccountingLedgerService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingLedgerController extends Controller
{
    public function __construct(
        private readonly AccountingLedgerService $ledgerService
    ) {}

    /**
     * Danh sách lịch sử chốt sổ
     */
    public function index(Request $request): JsonResponse
    {
        $ledgers = AccountingLedger::query()
            ->with(['property:id,name'])
            ->where('user_id', $request->user()->id)
            ->when($request->filled('property_id'), function ($q) use ($request) {
                $q->where('property_id', $request->property_id);
            })
            ->when($request->filled('period_year'), function ($q) use ($request) {
                $q->where('period_year', $request->period_year);
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate($request->integer('per_page', 15));

        return AccountingLedgerResource::collection($ledgers)->response();
    }

    /**
     * Xem trước số liệu (Dùng PreviewLedgerRequest)
     */
    public function preview(PreviewLedgerRequest $request): JsonResponse
    {
        // Request đã được validate an toàn
        $previewData = $this->ledgerService->previewLedger(
            $request->user()->id,
            $request->property_id ? (int) $request->property_id : null,
            $request->period_type,
            (int) $request->period_year,
            $request->period_month ? (int) $request->period_month : null,
            $request->boolean('include_deposit')
        );

        return response()->json([
            'data' => $previewData
        ]);
    }

    /**
     * Thực hiện chốt sổ (Dùng LockLedgerRequest)
     */
    public function store(LockLedgerRequest $request): JsonResponse
    {
        $ledger = $this->ledgerService->lockLedger(
            $request->user()->id,
            $request->validated()
        );

        $ledger->load('property:id,name');

        return (new AccountingLedgerResource($ledger))
            ->additional(['message' => 'Chốt sổ kế toán thành công.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Xem chi tiết sổ (Dùng ShowAccountingLedgerRequest check ownership)
     */
    public function show(ShowAccountingLedgerRequest $request, int $id): JsonResponse
    {
        // Vì request đã check ownership (tồn tại và thuộc về user) rồi, nên chỉ cần query lấy data
        $ledger = AccountingLedger::query()
            ->with(['property:id,name', 'details' => function ($q) {
                $q->orderBy('transaction_date', 'asc');
            }])
            ->find($id);

        return (new AccountingLedgerResource($ledger))->response();
    }

    /**
     * Hủy/Xóa sổ đã chốt (Dùng ShowAccountingLedgerRequest check ownership)
     */
    public function destroy(ShowAccountingLedgerRequest $request, int $id): JsonResponse
    {
        // Đã check ownership ở Request
        $ledger = AccountingLedger::find($id);

        $ledger->delete();

        return response()->json([
            'message' => 'Đã hủy sổ kế toán thành công.'
        ]);
    }

    public function previewHtml(ShowAccountingLedgerRequest $request, int $id, SettingService $settingService): JsonResponse
    {
        // Gọi qua SettingService để lấy HTML
        $html = $settingService->compileLedgerHtml($id, $request->user()->id);

        return response()->json([
            'data' => [
                'html' => $html
            ]
        ]);
    }
}
