<?php

declare(strict_types=1);

namespace App\Http\Resources\BankAccount; // Hãy sửa lại namespace nếu bạn đặt trong thư mục con

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\SepayConfig;

class BankAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Lấy cấu hình prefix của chủ trọ sở hữu tài khoản này
        // (Nếu họ chưa cấu hình, hàm này tự động trả về mặc định, VD: 'HD')
        $patternPrefix = SepayConfig::forUser((int) $this->user_id)->matchPattern();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'branch' => $this->branch,
            'is_default' => (bool) $this->is_default,

            // Magic nằm ở đây: Trả về link template QR cho Frontend
            'sepay_qr_template' => sprintf(
                'https://qr.sepay.vn/img?bank=%s&acc=%s&template=compact&amount={amount}&des={invoice_code}',
                $this->bank_code,
                $this->account_number,
                //$patternPrefix // Nối tiền tố vào trước mã (VD: HD{invoice_code}) và bỏ %s trên link template QR đi, vì Sepay sẽ tự động nhận diện tiền tố này
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}