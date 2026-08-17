<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use App\Models\SePayTransaction;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialTransactionService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Ghi nhận khách thanh toán hóa đơn bằng tiền mặt/chuyển khoản thủ công.
     *
     * Dùng cho API:
     * POST /invoices/{invoice}/receive-payment
     */
    public function receiveInvoicePayment(int $invoiceId, array $data, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($invoiceId, $data, $userId): FinancialTransaction {
            $invoice = $this->invoiceService->findOwnedInvoice($invoiceId, $userId);

            $this->assertInvoicePayable($invoice);

            $amount = (int) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new BusinessException('Số tiền thanh toán phải lớn hơn 0.');
            }

            if ($amount > (int) $invoice->remaining_amount) {
                throw new BusinessException('Số tiền thanh toán không được vượt quá số tiền còn nợ.');
            }

            $transactionDate = Carbon::parse($data['transaction_date'] ?? now());
            $limitDate = Carbon::parse($invoice->issue_date ?? $invoice->period_from)->startOfDay();

            if ($transactionDate->lt($limitDate)) {
                throw new BusinessException('Ngày thu tiền không hợp lệ (không được trước ngày hóa đơn được phát hành).');
            }

            // Tách số tiền thanh toán thành phần DOANH THU THẬT và phần TIỀN THẾ CHÂN (nếu hóa đơn có dòng deposit)
            $split = $this->splitPaymentForInvoice($invoice, $amount);

            $sharedFields = [
                'property_id' => $invoice->property_id,
                'room_id' => $invoice->room_id,
                'lease_id' => $invoice->lease_id,
                'tenant_id' => $invoice->lease->tenant_id ?? null,
                'tenant_name_snapshot' => $invoice->lease->tenant->full_name ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'sepay_transaction_id' => null,
                'method' => $data['method'] ?? 'cash',
                'status' => 'confirmed',
                'transaction_date' => $data['transaction_date'] ?? now(),
                'transfer_content' => $data['transfer_content'] ?? null,
                'bank_transaction_code' => $data['bank_transaction_code'] ?? null,
                'confirmed_at' => now(),
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ];

            $primaryTransaction = null;

            if ($split['revenue'] > 0) {
                $primaryTransaction = FinancialTransaction::create([
                    ...$sharedFields,
                    'transaction_code' => $this->generateTransactionCode('income'),
                    'direction' => 'income',
                    'category' => 'invoice_payment',
                    'accounting_type' => 'revenue',
                    'amount' => $split['revenue'],
                    'description' => $data['description'] ?? "Thanh toán hóa đơn {$invoice->invoice_code}",
                ]);

                $this->allocateToInvoice(
                    transaction: $primaryTransaction,
                    invoice: $invoice,
                    amount: $split['revenue'],
                    userId: $userId
                );
            }

            if ($split['deposit'] > 0) {
                $depositTransaction = FinancialTransaction::create([
                    ...$sharedFields,
                    'transaction_code' => $this->generateTransactionCode('income'),
                    'direction' => 'income',
                    'category' => 'security_deposit',
                    'accounting_type' => 'liability_in',
                    'amount' => $split['deposit'],
                    'description' => "Thu tiền thế chân qua hóa đơn {$invoice->invoice_code}",
                ]);

                $this->allocateToInvoice(
                    transaction: $depositTransaction,
                    invoice: $invoice,
                    amount: $split['deposit'],
                    userId: $userId
                );

                $primaryTransaction ??= $depositTransaction;
            }

            return $primaryTransaction->fresh([
                'property',
                'room',
                'lease',
                'tenant',
                'allocations.invoice',
            ]);
        });
    }

    /**
     * Tạo giao dịch thu từ SePay và cấn vào hóa đơn.
     *
     * Dùng bởi SePayTransactionService sau khi match được mã hóa đơn.
     */
    public function createInvoicePaymentFromSePay(
        Invoice $invoice,
        SePayTransaction $sePayTransaction,
        int $allocatedAmount,
        ?int $createdBy = null
    ): FinancialTransaction {
        return DB::transaction(function () use (
            $invoice,
            $sePayTransaction,
            $allocatedAmount,
            $createdBy
        ): FinancialTransaction {
            $invoice->refresh();

            $this->assertInvoicePayable($invoice);

            if ($allocatedAmount <= 0) {
                throw new BusinessException('Số tiền cấn vào hóa đơn phải lớn hơn 0.');
            }

            if ($allocatedAmount > (int) $invoice->remaining_amount) {
                throw new BusinessException('Số tiền cấn không được vượt quá số tiền còn nợ của hóa đơn.');
            }

            /*
             * Lưu ý:
             * - amount của financial_transactions là số tiền thực tế ngân hàng báo về.
             * - allocatedAmount là số tiền dùng để cấn vào hóa đơn.
             *
             * Trường hợp khách chuyển dư:
             * - transfer_amount = 2.000.000
             * - remaining_amount của hóa đơn = 1.750.000
             * - financial_transactions.amount = 2.000.000
             * - allocation.allocated_amount = 1.750.000
             * - SePay sẽ ở trạng thái partially_matched.
             *
             * Nếu hóa đơn có dòng "deposit" (tiền thế chân), phần allocatedAmount sẽ được TÁCH thành
             * 2 bản ghi financial_transactions: 1 phần revenue (doanh thu thật) + 1 phần liability_in
             * (tiền thế chân, không tính vào lợi nhuận). Phần chuyển dư (surplus) chưa cấn vào hóa đơn
             * nào vẫn giữ nguyên là revenue như hành vi cũ.
             */
            $transferAmount = (int) $sePayTransaction->transfer_amount;
            $surplus = max(0, $transferAmount - $allocatedAmount);

            $split = $this->splitPaymentForInvoice($invoice, $allocatedAmount);

            $sharedFields = [
                'property_id' => $invoice->property_id,
                'room_id' => $invoice->room_id,
                'lease_id' => $invoice->lease_id,
                'tenant_id' => $invoice->lease->tenant_id ?? null,
                'tenant_name_snapshot' => $invoice->lease->tenant->full_name ?? null,
                'bank_account_id' => $sePayTransaction->bank_account_id,
                'sepay_transaction_id' => $sePayTransaction->id,
                'method' => 'sepay',
                'status' => 'confirmed',
                'transaction_date' => $sePayTransaction->transaction_time ?? now(),
                'transfer_content' => $sePayTransaction->content,
                'bank_transaction_code' => $sePayTransaction->reference_code,
                'confirmed_at' => now(),
                'note' => null,
                'created_by' => $createdBy,
            ];

            $primaryTransaction = null;

            // Phần doanh thu thật (+ phần chuyển dư chưa cấn vào hóa đơn nào, giữ nguyên hành vi cũ)
            $revenuePortion = $split['revenue'] + $surplus;
            if ($revenuePortion > 0) {
                $primaryTransaction = FinancialTransaction::create([
                    ...$sharedFields,
                    'transaction_code' => $this->generateTransactionCode('income'),
                    'direction' => 'income',
                    'category' => 'invoice_payment',
                    'accounting_type' => 'revenue',
                    'amount' => $revenuePortion,
                    'description' => "SePay thanh toán hóa đơn {$invoice->invoice_code}",
                ]);

                if ($split['revenue'] > 0) {
                    $this->allocateToInvoice(
                        transaction: $primaryTransaction,
                        invoice: $invoice,
                        amount: $split['revenue'],
                        userId: $createdBy
                    );
                }
            }

            if ($split['deposit'] > 0) {
                $depositTransaction = FinancialTransaction::create([
                    ...$sharedFields,
                    'transaction_code' => $this->generateTransactionCode('income'),
                    'direction' => 'income',
                    'category' => 'security_deposit',
                    'accounting_type' => 'liability_in',
                    'amount' => $split['deposit'],
                    'description' => "SePay thu tiền thế chân qua hóa đơn {$invoice->invoice_code}",
                ]);

                $this->allocateToInvoice(
                    transaction: $depositTransaction,
                    invoice: $invoice,
                    amount: $split['deposit'],
                    userId: $createdBy
                );

                $primaryTransaction ??= $depositTransaction;
            }

            return $primaryTransaction->fresh([
                'property',
                'room',
                'lease',
                'tenant',
                'allocations.invoice',
            ]);
        });
    }


    /**
     * Tách 1 khoản thanh toán/cấn vào hóa đơn thành 2 phần (Ưu tiên thu THẾ CHÂN trước):
     * - 'deposit': phần tiền thế chân (charge_type = 'deposit'), sẽ được thu trước tiên.
     * - 'revenue': phần doanh thu thật (tiền phòng, điện, nước...), sẽ thu khi phần thế chân đã đóng đủ.
     */
    private function splitPaymentForInvoice(Invoice $invoice, int $amount): array
    {
        // 1. Tính tổng số tiền thế chân yêu cầu của hóa đơn
        $depositAmount = (int) $invoice->items()->where('charge_type', 'deposit')->sum('amount');

        // Nếu hóa đơn không có hạng mục tiền thế chân, 100% tiền vào doanh thu
        if ($depositAmount <= 0) {
            return ['revenue' => $amount, 'deposit' => 0];
        }

        // 2. Tính số tiền thế chân đã được thu ở các lần thanh toán trả góp trước đó (nếu có)
        $depositAlreadyRecognized = (int) FinancialTransaction::query()
            ->whereHas('allocations', fn($q) => $q->where('invoice_id', $invoice->id))
            ->where('category', 'security_deposit')
            ->where('accounting_type', 'liability_in')
            ->sum('amount');

        // 3. Số tiền thế chân CÒN NỢ cần thu thêm
        $depositRemaining = max(0, $depositAmount - $depositAlreadyRecognized);

        // Nếu tiền thế chân đã thu đủ từ trước, 100% tiền đợt này vào doanh thu
        if ($depositRemaining <= 0) {
            return ['revenue' => $amount, 'deposit' => 0];
        }

        // 4. LOGIC MỚI: ƯU TIÊN THẾ CHÂN TRƯỚC (Waterfall Allocation)
        // Lấy số tiền thanh toán đập vào thế chân trước, tối đa bằng số thế chân còn nợ
        $depositPortion = min($amount, $depositRemaining);

        // Phần còn thừa (nếu có) sau khi thu cọc xong mới tính là doanh thu
        $revenuePortion = $amount - $depositPortion;

        return [
            'revenue' => $revenuePortion,
            'deposit' => $depositPortion,
        ];
    }

    /**
     * Tạo thu/chi không liên quan hóa đơn.
     *
     * Ví dụ:
     * - thu cọc giữ chỗ,
     * - thu tiền thế chân,
     * - tịch thu cọc,
     * - hoàn thế chân,
     * - chi sửa phòng,
     * - chi vận hành.
     */
    public function createGeneralTransaction(array $data, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($data, $userId): FinancialTransaction {
            $amount = (int) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new BusinessException('Số tiền thu/chi phải lớn hơn 0.');
            }

            $direction = $data['direction'] ?? null;

            if (!in_array($direction, ['income', 'expense'], true)) {
                throw new BusinessException('Chiều giao dịch không hợp lệ.');
            }

            return FinancialTransaction::create([
                'property_id' => $data['property_id'],
                'room_id' => $data['room_id'] ?? null,
                'lease_id' => $data['lease_id'] ?? null,
                'tenant_id' => $data['tenant_id'] ?? null,
                'tenant_name_snapshot' => ($data['tenant_id'] ?? null) 
                    ? Tenant::find($data['tenant_id'])?->full_name
                    : null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'sepay_transaction_id' => $data['sepay_transaction_id'] ?? null,

                'transaction_code' => $this->generateTransactionCode($direction),

                'direction' => $direction,
                'category' => $data['category'],
                'accounting_type' => $data['accounting_type'],

                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'status' => $data['status'] ?? 'confirmed',

                'transaction_date' => $data['transaction_date'] ?? now(),

                'transfer_content' => $data['transfer_content'] ?? null,
                'bank_transaction_code' => $data['bank_transaction_code'] ?? null,

                'confirmed_at' => ($data['status'] ?? 'confirmed') === 'confirmed' ? now() : null,

                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,

                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Cấn một khoản thu vào hóa đơn.
     *
     * Quy tắc:
     * - Giao dịch phải cùng property với hóa đơn.
     * - Tổng số tiền đã cấn của transaction không được vượt quá transaction.amount.
     * - Số tiền cấn không được vượt quá remaining_amount của hóa đơn.
     */
    public function allocateToInvoice(
        FinancialTransaction $transaction,
        Invoice $invoice,
        int $amount,
        ?int $userId = null
    ): FinancialTransactionAllocation {
        return DB::transaction(function () use ($transaction, $invoice, $amount, $userId): FinancialTransactionAllocation {
            $transaction->refresh();
            $invoice->refresh();

            if ($transaction->status !== 'confirmed') {
                throw new BusinessException('Chỉ có thể cấn tiền từ giao dịch đã xác nhận.');
            }

            if ($transaction->direction !== 'income') {
                throw new BusinessException('Chỉ có thể cấn tiền từ giao dịch thu.');
            }

            if ((int) $transaction->property_id !== (int) $invoice->property_id) {
                throw new BusinessException('Giao dịch thu và hóa đơn không cùng khu nhà.');
            }

            if ($invoice->status === 'cancelled') {
                throw new BusinessException('Không thể cấn tiền vào hóa đơn đã hủy.');
            }

            if ($invoice->status === 'draft') {
                throw new BusinessException('Không thể cấn tiền vào hóa đơn chưa phát hành.');
            }

            if ($invoice->status === 'paid') {
                throw new BusinessException('Hóa đơn đã thanh toán đủ.');
            }

            if ($amount <= 0) {
                throw new BusinessException('Số tiền cấn phải lớn hơn 0.');
            }

            $alreadyAllocatedOfTransaction = (int) FinancialTransactionAllocation::query()
                ->where('financial_transaction_id', $transaction->id)
                ->where('allocation_type', 'payment')
                ->sum('allocated_amount');

            $availableAmount = (int) $transaction->amount - $alreadyAllocatedOfTransaction;

            if ($amount > $availableAmount) {
                throw new BusinessException('Số tiền cấn vượt quá số tiền còn lại của giao dịch thu.');
            }

            if ($amount > (int) $invoice->remaining_amount) {
                throw new BusinessException('Số tiền cấn vượt quá số tiền còn nợ của hóa đơn.');
            }

            $exists = FinancialTransactionAllocation::query()
                ->where('financial_transaction_id', $transaction->id)
                ->where('invoice_id', $invoice->id)
                ->exists();

            if ($exists) {
                throw new BusinessException('Giao dịch này đã được cấn vào hóa đơn này trước đó.');
            }

            $allocation = FinancialTransactionAllocation::create([
                'financial_transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $amount,
                'allocation_type' => 'payment',
                'allocated_at' => now(),
                'created_by' => $userId,
            ]);

            $this->invoiceService->refreshPaymentStatus($invoice);

            return $allocation->fresh(['financialTransaction', 'invoice']);
        });
    }

    private function assertInvoicePayable(Invoice $invoice): void
    {
        if ($invoice->status === 'draft') {
            throw new BusinessException('Không thể thanh toán hóa đơn chưa phát hành.');
        }

        if ($invoice->status === 'cancelled') {
            throw new BusinessException('Không thể thanh toán hóa đơn đã hủy.');
        }

        if ($invoice->status === 'paid') {
            throw new BusinessException('Hóa đơn đã được thanh toán đủ.');
        }

        if ((int) $invoice->remaining_amount <= 0) {
            throw new BusinessException('Hóa đơn không còn công nợ cần thanh toán.');
        }
    }

    /**
     * Sinh mã phiếu thu/chi.
     *
     * PT = phiếu thu.
     * PC = phiếu chi.
     */
    private function generateTransactionCode(string $direction): string
    {
        $prefix = $direction === 'expense' ? 'PC' : 'PT';

        do {
            $code = sprintf(
                '%s-%s-%s',
                $prefix,
                now()->format('Ym'),
                Str::upper(Str::random(5))
            );
        } while (FinancialTransaction::query()->where('transaction_code', $code)->exists());

        return $code;
    }
}
