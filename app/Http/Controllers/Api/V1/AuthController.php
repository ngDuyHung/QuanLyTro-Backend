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
use App\Http\Requests\Auth\ZaloLoginRequest;
use App\Http\Requests\Auth\ZaloLinkRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

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

    public function zaloLogin(ZaloLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWithZalo($request->validated());

        if (($result['requires_link'] ?? false) === true) {
            return response()->json([
                'message'       => 'Tài khoản Zalo chưa được liên kết. Vui lòng đăng nhập bằng số điện thoại và mật khẩu để liên kết lần đầu.',
                'requires_link' => true,
                'link_token'    => $result['link_token'],
            ]);
        }

        return response()->json([
            'message'      => 'Đăng nhập bằng Zalo thành công.',
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

    /**
     * Cập nhật thông tin cá nhân (Tên, Email)
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            // Validate email duy nhất nhưng bỏ qua user hiện tại
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ], [
            'name.required' => 'Họ và tên không được để trống.',
            'email.unique'  => 'Email này đã được sử dụng bởi một tài khoản khác.',
            'email.email'   => 'Định dạng email không hợp lệ.',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật thông tin cá nhân thành công.',
            'user'    => new UserResource($user)
        ]);
    }

    /**
     * Đổi mật khẩu
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            // confirmed yêu cầu field new_password_confirmation phải gửi lên và khớp nhau
            'new_password'     => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'new_password.required'     => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min'          => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'new_password.confirmed'    => 'Xác nhận mật khẩu mới không khớp.'
        ]);

        $user = $request->user();

        // Kiểm tra mật khẩu cũ có đúng không
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không chính xác.']
            ]);
        }

        // Cập nhật mật khẩu mới
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công.'
        ]);
    }
}
