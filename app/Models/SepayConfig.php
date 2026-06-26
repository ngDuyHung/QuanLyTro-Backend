<?php

declare(strict_types=1);

namespace App\Models;

class SepayConfig
{
    public function __construct(private readonly int $userId) {}

    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    // Đọc một cấu hình (Nếu không có trả về default)
    private function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('user_id', $this->userId)->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    // Ghi một loạt cấu hình
    public function save(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                Setting::where('user_id', $this->userId)->where('key', $key)->delete();
            } else {
                Setting::updateOrCreate(
                    ['user_id' => $this->userId, 'key' => $key],
                    ['value' => (string) $value]
                );
            }
        }
    }

    // Xóa toàn bộ cấu hình SePay của user này
    public function clear(): void
    {
        Setting::where('user_id', $this->userId)->where('key', 'like', 'sepay_%')->delete();
    }

    public function apiToken(): ?string
    {
        return $this->get('sepay_api_token');
    }

    public function webhookSecret(): ?string
    {
        return $this->get('sepay_webhook_secret');
    }

    public function autoConfirm(): bool
    {
        return $this->get('sepay_auto_confirm', 'true') === 'true';
    }

    public function matchPattern(): string
    {
        // Lấy tiền tố mặc định từ file .env (nếu hệ thống có cài đặt sẵn)
        $defaultPattern = config('sepay.pattern', env('SEPAY_MATCH_PATTERN', 'HD'));

        // Trả về cấu hình riêng của chủ trọ, nếu chưa có thì dùng mặc định
        return $this->get('sepay_match_pattern', $defaultPattern);
    }
    public function isConnected(): bool
    {
        return !empty($this->apiToken());
    }

    // Trả về dạng mảng an toàn cho API (Che giấu token)
    public function toArray(): array
    {
        $token = $this->apiToken();
        $tokenHint = $token && strlen($token) > 4
            ? '••••••••' . substr($token, -4)
            : null;

        return [
            'is_connected' => $this->isConnected(),
            'has_api_token' => !empty($token),
            'has_webhook_secret' => !empty($this->webhookSecret()),
            'auto_confirm' => $this->autoConfirm(),
            'match_pattern' => $this->matchPattern(),
            'api_token_hint' => $tokenHint,
        ];
    }
}
