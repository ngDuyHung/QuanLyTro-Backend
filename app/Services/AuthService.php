<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\Domain\BusinessException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * @return array{user: User, access_token: string, token_type: string, expires_at: string|null}
     * @throws BusinessException
     */
    public function login(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            throw new BusinessException('Email hoặc mật khẩu không đúng.', 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            throw new BusinessException('Tài khoản đã bị vô hiệu hóa.', 403);
        }

        // Xóa token cũ cùng tên trước khi cấp mới
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
     */
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            $data['role']      = UserRole::ChuTro->value;
            $data['is_active'] = true;

            return $this->userRepository->create($data);
        });

        $token = $user->createToken('api_token', expiresAt: now()->addDays(30));

        return [
            'user'         => $user,
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $token->accessToken->expires_at?->toISOString(),
        ];
    }


    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
