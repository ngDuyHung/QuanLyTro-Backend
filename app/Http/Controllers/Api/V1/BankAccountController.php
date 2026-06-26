<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\StoreBankAccountRequest;
use App\Http\Requests\BankAccount\UpdateBankAccountRequest;
use App\Http\Resources\BankAccount\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    /**
     * READ ALL: Danh sách tài khoản ngân hàng của chủ trọ
     */
    public function index(Request $request): JsonResponse
    {
        $bankAccounts = BankAccount::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return BankAccountResource::collection($bankAccounts)->response();
    }

    /**
     * CREATE: Thêm mới tài khoản ngân hàng
     */
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $validated = $request->validated();

        $bankAccount = DB::transaction(function () use ($userId, $validated) {
            // Nếu đánh dấu là mặc định hoặc đây là tài khoản đầu tiên, ép các tài khoản khác về false
            $isDefault = filter_var($validated['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isFirstAccount = !BankAccount::where('user_id', $userId)->exists();

            if ($isDefault || $isFirstAccount) {
                BankAccount::where('user_id', $userId)->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            $validated['user_id'] = $userId;
            return BankAccount::create($validated);
        });

        return (new BankAccountResource($bankAccount))
            ->response()
            ->setStatusCode(21);
    }

    /**
     * READ SINGLE: Xem chi tiết một tài khoản
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $bankAccount = BankAccount::where('user_id', $request->user()->id)->findOrFail($id);
        return (new BankAccountResource($bankAccount))->response();
    }

    /**
     * UPDATE: Cập nhật tài khoản ngân hàng
     */
    public function update(UpdateBankAccountRequest $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $bankAccount = BankAccount::where('user_id', $userId)->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($userId, $bankAccount, $validated) {
            if (isset($validated['is_default']) && filter_var($validated['is_default'], FILTER_VALIDATE_BOOLEAN)) {
                BankAccount::where('user_id', $userId)
                    ->where('id', '!=', $bankAccount->id)
                    ->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            $bankAccount->update($validated);
        });

        return (new BankAccountResource($bankAccount->fresh()))->response();
    }

    /**
     * DELETE: Xóa tài khoản ngân hàng
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $bankAccount = BankAccount::where('user_id', $request->user()->id)->findOrFail($id);

        // Kiểm tra xem tài khoản này có đang ràng buộc với giao dịch tài chính nào không
        if ($bankAccount->financialTransactions()->exists() || $bankAccount->sepayTransactions()->exists()) {
            throw new BusinessException('Không thể xóa tài khoản ngân hàng đã phát sinh lịch sử giao dịch. Hãy ẩn hoặc giữ nguyên.');
        }

        $bankAccount->delete();

        return response()->json([
            'message' => 'Xóa tài khoản ngân hàng thành công.',
        ]);
    }
}