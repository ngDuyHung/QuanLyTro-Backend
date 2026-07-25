<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\SePayTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SePayTransactionService
{
    public function __construct(
        private readonly FinancialTransactionService $financialTransactionService
    ) {}

    /**
     * Xử lý webhook từ SePay.
     *
     * Luồng chính:
     * 1. Chuẩn hóa payload.
     * 2. Chống trùng giao dịch.
     * 3. Lưu sepay_transactions.
     * 4. Nếu là tiền vào thì parse mã hóa đơn từ nội dung chuyển khoản.
     * 5. Nếu match được hóa đơn thì tạo financial_transactions.
     * 6. Cấn tiền vào hóa đơn qua financial_transaction_allocations.
     */
    public function handleWebhook(object|array $payload, mixed $info = null): SePayTransaction
    {
        return DB::transaction(function () use ($payload, $info): SePayTransaction {
            $data = $this->normalizePayload($payload);

            $providerTransactionId = $this->stringOrNull($data['id'] ?? null);

            if ($providerTransactionId) {
                $existing = SePayTransaction::query()
                    ->where('provider_transaction_id', $providerTransactionId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'match_status' => $existing->match_status === 'matched'
                            ? 'matched'
                            : 'duplicated',
                        'error_message' => $existing->match_status === 'matched'
                            ? $existing->error_message
                            : 'Webhook trùng provider_transaction_id.',
                    ]);

                    return $existing;
                }
            }

            $bankAccount = $this->findBankAccount(
                $data['accountNumber'] ?? null,
                $data['subAccount'] ?? null
            );

            $transaction = SePayTransaction::create([
                'bank_account_id' => $bankAccount?->id,

                'provider_transaction_id' => $providerTransactionId,
                'reference_code' => $this->stringOrNull($data['referenceCode'] ?? null),
                'gateway' => $this->stringOrNull($data['gateway'] ?? null),

                'transaction_time' => $this->parseDateTime($data['transactionDate'] ?? null),
                'received_at' => now(),

                'account_number' => $this->stringOrNull($data['accountNumber'] ?? null),
                'sub_account' => $this->stringOrNull($data['subAccount'] ?? null),

                'code' => $this->stringOrNull($data['code'] ?? null),
                'content' => $this->stringOrNull($data['content'] ?? null),
                'description' => $this->stringOrNull($data['description'] ?? null),

                'matched_payment_code' => null,

                'transfer_type' => $this->normalizeTransferType($data['transferType'] ?? null),
                'transfer_amount' => (int) ($data['transferAmount'] ?? 0),
                'accumulated' => (int) ($data['accumulated'] ?? 0),

                'match_status' => 'unmatched',
                'matched_amount' => 0,

                'raw_payload' => $data,
            ]);

            return $this->processMatching($transaction, $info);
        });
    }



    /**
     * Match thủ công một giao dịch SePay vào một hóa đơn.
     *
     * Dùng cho màn hình đối soát:
     * POST /sepay-transactions/{id}/match
     */
    public function manualMatch(int $sePayTransactionId, int $invoiceId, int $userId): SePayTransaction
    {
        return DB::transaction(function () use ($sePayTransactionId, $invoiceId, $userId): SePayTransaction {
            $sePayTransaction = SePayTransaction::query()->findOrFail($sePayTransactionId);

            if (!in_array($sePayTransaction->match_status, ['unmatched', 'need_review', 'partially_matched'], true)) {
                throw new BusinessException('Giao dịch SePay này không còn ở trạng thái có thể đối soát thủ công.');
            }

            if ($sePayTransaction->transfer_type !== 'in') {
                throw new BusinessException('Chỉ có thể đối soát giao dịch tiền vào.');
            }

            $invoice = Invoice::query()
                ->with(['lease.room.property', 'items'])
                ->whereHas('lease.room.property', function ($query) use ($userId): void {
                    $query->where('user_id', $userId);
                })
                ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                ->findOrFail($invoiceId);

            $remainingSePayAmount = (int) $sePayTransaction->transfer_amount - (int) $sePayTransaction->matched_amount;

            if ($remainingSePayAmount <= 0) {
                throw new BusinessException('Giao dịch SePay này không còn số tiền chưa đối soát.');
            }

            if ((int) $invoice->remaining_amount <= 0) {
                throw new BusinessException('Hóa đơn đã thanh toán đủ.');
            }

            $allocatedAmount = min(
                $remainingSePayAmount,
                (int) $invoice->remaining_amount
            );

            $this->financialTransactionService->createInvoicePaymentFromSePay(
                invoice: $invoice,
                sePayTransaction: $sePayTransaction,
                allocatedAmount: $allocatedAmount,
                createdBy: $userId
            );

            $newMatchedAmount = (int) $sePayTransaction->matched_amount + $allocatedAmount;

            $sePayTransaction->update([
                'matched_payment_code' => $invoice->invoice_code,
                'matched_amount' => $newMatchedAmount,
                'match_status' => $newMatchedAmount >= (int) $sePayTransaction->transfer_amount
                    ? 'matched'
                    : 'partially_matched',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return $sePayTransaction->fresh();
        });
    }

    /**
     * Chuẩn hóa payload object/array thành array.
     */
    private function normalizePayload(object|array $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        return json_decode(json_encode($payload), true) ?: [];
    }

    /**
     * Tìm tài khoản ngân hàng theo subAccount (ưu tiên) hoặc accountNumber.
     */
    private function findBankAccount(mixed $accountNumber, mixed $subAccount = null): ?BankAccount
    {
        $accountNumber = $this->stringOrNull($accountNumber);
        $subAccount = $this->stringOrNull($subAccount);

        if (!$accountNumber && !$subAccount) {
            return null;
        }

        return BankAccount::query()
            ->when($subAccount, function ($query) use ($subAccount) {
                $query->where('account_number', $subAccount);
            })
            ->orWhere('account_number', $accountNumber)
            ->first();
    }

    /**
     * Chuẩn hóa transferType.
     */
    private function normalizeTransferType(mixed $transferType): string
    {
        return $transferType === 'out' ? 'out' : 'in';
    }

    /**
     * Parse ngày giờ từ webhook.
     */
    private function parseDateTime(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }


    public function processMatching(SePayTransaction $sePayTransaction, mixed $info = null): SePayTransaction
    {
        return DB::transaction(function () use ($sePayTransaction, $info): SePayTransaction {
            $sePayTransaction->refresh();

            if ($sePayTransaction->transfer_type !== 'in') {
                $sePayTransaction->update([
                    'match_status' => 'ignored',
                    'processed_at' => now(),
                    'error_message' => 'Bỏ qua giao dịch tiền ra.',
                ]);
                return $sePayTransaction->fresh();
            }

            // 1. Tìm chủ sở hữu của tài khoản ngân hàng nhận tiền
            $bankAccount = $sePayTransaction->bankAccount;
            if (!$bankAccount) {
                $sePayTransaction->update([
                    'match_status' => 'need_review',
                    'error_message' => 'Không tìm thấy tài khoản ngân hàng nhận tiền trong hệ thống.',
                ]);
                return $sePayTransaction->fresh();
            }

            $landlordId = $bankAccount->user_id;

            // 2. Lấy cấu hình của chủ trọ đó (Tiền tố mã Hóa đơn & Check duyệt tay)
            $sepayConfig = \App\Models\SepayConfig::forUser((int) $landlordId);
            $patternPrefix = $sepayConfig->matchPattern();
            $isAutoApprove = $sepayConfig->autoConfirm();

            $paymentCode = $this->extractPaymentCode(
                content: (string) $sePayTransaction->content,
                patternPrefix: $patternPrefix,
                code: $sePayTransaction->code ? (string) $sePayTransaction->code : null,
                info: $info
            );

            if (!$paymentCode) {
                $sePayTransaction->update([
                    'match_status' => 'need_review',
                    'processed_at' => now(),
                    'error_message' => 'Không tìm thấy mã hóa đơn trong nội dung chuyển khoản.',
                ]);
                return $sePayTransaction->fresh();
            }

            $sePayTransaction->update(['matched_payment_code' => $paymentCode]);

            $invoice = Invoice::query()
                ->with(['lease.room.property', 'items'])
                ->where('invoice_code', $paymentCode)
                ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                ->first();

            if (!$invoice) {
                $sePayTransaction->update([
                    'match_status' => 'need_review',
                    'processed_at' => now(),
                    'error_message' => "Không tìm thấy hóa đơn hợp lệ với mã {$paymentCode}.",
                ]);
                return $sePayTransaction->fresh();
            }

            // 3. Logic chặn duyệt tự động nếu chủ trọ cấu hình DUYỆT TAY
            if (!$isAutoApprove) {
                $sePayTransaction->update([
                    'match_status' => 'need_review',
                    'processed_at' => now(),
                    'error_message' => 'Giao dịch khớp mã hóa đơn. Đang chờ chủ trọ duyệt thủ công.',
                ]);
                return $sePayTransaction->fresh();
            }

            $transferAmount = (int) $sePayTransaction->transfer_amount;
            $remainingAmount = (int) $invoice->remaining_amount;
            $allocatedAmount = min($transferAmount, $remainingAmount);

            try {
                $this->financialTransactionService->createInvoicePaymentFromSePay(
                    invoice: $invoice,
                    sePayTransaction: $sePayTransaction,
                    allocatedAmount: $allocatedAmount,
                    createdBy: null
                );

                $matchStatus = $allocatedAmount === $transferAmount ? 'matched' : 'partially_matched';
                $sePayTransaction->update([
                    'match_status' => $matchStatus,
                    'matched_amount' => $allocatedAmount,
                    'processed_at' => now(),
                ]);

                return $sePayTransaction->fresh();
            } catch (\Throwable $exception) {
                $sePayTransaction->update([
                    'match_status' => 'need_review',
                    'processed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);
                return $sePayTransaction->fresh();
            }
        });
    }

    // Đã thêm tham số patternPrefix lấy từ DB thay vì file Env cứng
    //Hàm này sẽ tìm kiếm mã hóa đơn trong nội dung chuyển khoản hoặc code hoặc info (nếu có) dựa trên tiền tố patternPrefix.
    private function extractPaymentCode(string $content, string $patternPrefix, ?string $code = null, mixed $info = null): ?string
    {
        $candidates = [
            $this->stringOrNull($info),
            $this->stringOrNull($code),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && str_starts_with(strtoupper($candidate), strtoupper($patternPrefix))) {
                return strtoupper($candidate);
            }
        }

        $haystack = strtoupper($content);
        $regex = '/(' . preg_quote(strtoupper($patternPrefix), '/') . '[A-Z0-9\-_]+)/';

        if (preg_match($regex, $haystack, $matches)) {
            return $matches[1];
        }

        return null;
    }


    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
