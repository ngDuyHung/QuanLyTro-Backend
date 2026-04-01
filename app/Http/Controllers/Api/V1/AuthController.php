<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\User\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'message'      => 'Đăng nhập thành công.',
            'user'         => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'token_type'   => $result['token_type'],
            'expires_at'   => $result['expires_at'],
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message'      => 'Đăng ký thành công.',
            'user'         => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'token_type'   => $result['token_type'],
            'expires_at'   => $result['expires_at'],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
