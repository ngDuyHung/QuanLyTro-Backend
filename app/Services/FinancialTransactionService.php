<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use App\Models\SePayTransaction;
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

            $transaction = FinancialTransaction::create([
                'property_id' => $invoice->property_id,
                'room_id' => $invoice->room_id,
                'lease_id' => $invoice->lease_id,
                'tenant_id' => $invoice->lease->tenant_id ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'sepay_transaction_id' => null,

                'transaction_code' => $this->generateTransactionCode('income'),

                'direction' => 'income',
                'category' => 'invoice_payment',
                'accounting_type' => 'revenue',

                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'status' => 'confirmed',

                'transaction_date' => $data['transaction_date'] ?? now(),

                'transfer_content' => $data['transfer_content'] ?? null,
                'bank_transaction_code' => $data['bank_transaction_code'] ?? null,

                'confirmed_at' => now(),

                'description' => $data['description'] ?? "Thanh toán hóa đơn {$invoice->invoice_code}",
                'note' => $data['note'] ?? null,

                'created_by' => $userId,
            ]);

            $this->allocateToInvoice(
                transaction: $transaction,
                invoice: $invoice,
                amount: $amount,
                userId: $userId
            );

            return $transaction->fresh([
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
             */
            $transaction = FinancialTransaction::create([
                'property_id' => $invoice->property_id,
                'room_id' => $invoice->room_id,
                'lease_id' => $invoice->lease_id,
                'tenant_id' => $invoice->lease->tenant_id ?? null,
                'bank_account_id' => $sePayTransaction->bank_account_id,
                'sepay_transaction_id' => $sePayTransaction->id,

                'transaction_code' => $this->generateTransactionCode('income'),

                'direction' => 'income',
                'category' => 'invoice_payment',
                'accounting_type' => 'revenue',

                'amount' => (int) $sePayTransaction->transfer_amount,
                'method' => 'sepay',
                'status' => 'confirmed',

                'transaction_date' => $sePayTransaction->transaction_time ?? now(),

                'transfer_content' => $sePayTransaction->content,
                'bank_transaction_code' => $sePayTransaction->reference_code,

                'confirmed_at' => now(),

                'description' => "SePay thanh toán hóa đơn {$invoice->invoice_code}",
                'note' => null,

                'created_by' => $createdBy,
            ]);

            $this->allocateToInvoice(
                transaction: $transaction,
                invoice: $invoice,
                amount: $allocatedAmount,
                userId: $createdBy
            );

            return $transaction->fresh([
                'property',
                'room',
                'lease',
                'tenant',
                'allocations.invoice',
            ]);
        });
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