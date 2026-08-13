<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthService
{


    /**
     * @return array{user: User, access_token: string, token_type: string, expires_at: string|null}
     */
    private function issueToken(User $user): array
    {
        $user->tokens()->where('name', 'api_token')->delete();

        $token = $user->createToken('api_token', expiresAt: now()->addDays(30));

        return [
            'user'         => $user,
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $token->accessToken->expires_at?->toISOString(),
        ];
    }

    /**
     * @return array{user: User, access_token: string, token_type: string, expires_at: string|null}
     * @throws BusinessException
     */
    public function login(array $data): array
    {
        $credentials = [
            'phone'    => $data['phone'],
            'password' => $data['password'],
        ];

        if (! Auth::attempt($credentials)) {
            throw new BusinessException('Số điện thoại hoặc mật khẩu không đúng.', 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw new BusinessException('Tài khoản đã bị vô hiệu hóa.', 403);
        }

        $this->linkZaloToUserIfNeeded(
            $user,
            $data['zalo_link_token'] ?? null
        );

        return $this->issueToken($user->refresh());
    }

    /**
     * @return array{
     *     user?: User,
     *     access_token?: string,
     *     token_type?: string,
     *     expires_at?: string|null,
     *     requires_link?: bool,
     *     link_token?: string
     * }
     */
    public function loginWithZalo(array $data): array
    {
        $zaloAccessToken = $this->getZaloAccessToken(
            $data['code'],
            $data['code_verifier']
        );

        $zaloUser = $this->getZaloUserInfo($zaloAccessToken);

        $zaloId = (string) ($zaloUser['id'] ?? '');

        if ($zaloId === '') {
            throw new BusinessException('Không lấy được thông tin tài khoản Zalo.', 401);
        }

        $user = User::where('zalo_id', $zaloId)->first();

        if (! $user) {
            $linkToken = Str::random(64);

            Cache::put("zalo_link:{$linkToken}", [
                'zalo_id' => $zaloId,
            ], now()->addMinutes(5));

            return [
                'requires_link' => true,
                'link_token'    => $linkToken,
            ];
        }

        if (! $user->is_active) {
            throw new BusinessException('Tài khoản đã bị vô hiệu hóa.', 403);
        }

        return $this->issueToken($user);
    }

    // Nếu linkToken tồn tại, liên kết tài khoản Zalo với user hiện tại (nếu chưa liên kết) sau khi đăng nhập thành công bằng số điện thoại/mật khẩu
    private function linkZaloToUserIfNeeded(User $user, ?string $linkToken): void
    {
        if (blank($linkToken)) {
            return;
        }

        $cacheKey = "zalo_link:{$linkToken}";

        $payload = Cache::get($cacheKey);

        if (! $payload || empty($payload['zalo_id'])) {
            throw new BusinessException('Phiên liên kết Zalo đã hết hạn hoặc không hợp lệ.', 401);
            //return;
        }

        $zaloId = (string) $payload['zalo_id'];

        if (! empty($user->zalo_id) && $user->zalo_id !== $zaloId) {
            throw new BusinessException('Tài khoản này đã được liên kết với Zalo khác.', 409);
        }

        $exists = User::where('zalo_id', $zaloId)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            throw new BusinessException('Tài khoản Zalo này đã được liên kết với tài khoản khác.', 409);
        }

        $user->forceFill([
            'zalo_id'        => $zaloId,
            'zalo_linked_at' => now(),
        ])->save();

        Cache::forget($cacheKey);
    }

    /**
     * @return array{user: User, access_token: string, token_type: string, expires_at: string|null}
     */
    public function register(array $data): array
    {
        $data['password']  = Hash::make($data['password']);
        $data['is_active'] = true;

        $user = User::create($data);

        $user->assignRole('landlord');

        return $this->issueToken($user);
    }

    //Hàm tạo tài khoản cho người thuê đại diện hợp đồng
    public function registerTenant(array $data): User
    {
        $data['password']  = Hash::make($data['password']);
        $data['is_active'] = true;

        $user = User::create($data);

        $user->assignRole('tenant');

        return $user;
    }

    /**
     * Lấy tài khoản Tenant đã có hoặc tạo mới nếu chưa tồn tại (chống trùng SĐT)
     */
    public function getOrCreateTenantUser(array $data): User
    {
        $phone = $data['phone'];

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $data['password']  = Hash::make($data['password'] ?? $phone);
            $data['is_active'] = true;

            $user = User::create($data);
            $user->assignRole('tenant');
        }

        return $user;
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }


    // Lấy access token từ Zalo bằng authorization code
    private function getZaloAccessToken(string $code, string $codeVerifier): string
    {
        $response = Http::asForm()
            ->withHeaders([
                'secret_key' => config('services.zalo.app_secret'),
            ])
            ->post('https://oauth.zaloapp.com/v4/access_token', [
                'app_id'        => config('services.zalo.app_id'),
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ]);

        if (! $response->successful()) {
            throw new BusinessException('Không thể xác thực với Zalo.', 401);
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new BusinessException(
                $data['error_description']
                    ?? $data['error_name']
                    ?? 'Zalo không trả về access token.',
                401
            );
        }

        return (string) $data['access_token'];
    }

    // Lấy thông tin người dùng từ Zalo Graph API
    private function getZaloUserInfo(string $accessToken): array
    {
        $response = Http::withHeaders([
            'access_token' => $accessToken,
        ])->get('https://graph.zalo.me/v2.0/me', [
            'fields' => 'id,name,picture',
        ]);

        if (! $response->successful()) {
            throw new BusinessException('Không thể lấy thông tin người dùng Zalo.', 401);
        }

        $data = $response->json();

        if (($data['error'] ?? 0) !== 0) {
            throw new BusinessException('Không thể lấy thông tin người dùng Zalo.', 401);
        }

        return $data;
    }
    // Chuẩn hóa số điện thoại Việt Nam: loại bỏ ký tự không phải số, chuyển đầu +84 thành 0, kiểm tra định dạng
    private function normalizeVietnamesePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($phone, '84') && strlen($phone) === 11) {
            $phone = '0' . substr($phone, 2);
        }

        if (! preg_match('/^0[0-9]{9}$/', $phone)) {
            return '';
        }

        return $phone;
    }
}
