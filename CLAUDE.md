# CLAUDE.md — Backend Rules (Laravel)

> Quy tắc cho dự án **QuanLyTro** (đồ án tốt nghiệp).
> Ưu tiên: code rõ ràng, đúng kiến trúc, dễ mở rộng — nhưng **không over-engineer**.
> Bỏ qua: Events/Listeners, Queue/Jobs, Cache, Testing bắt buộc (làm sau nếu còn thời gian).

---

## 1. STACK & VERSIONS (Nếu project có sẵn rồi thì thôi)

```
PHP        : 8.2+
Laravel    : 11+
Database   : MySQL 8.0+
Auth       : Laravel Sanctum
Testing    : PHPUnit + Pest
```

---

## 2. KIẾN TRÚC TỔNG QUAN

Áp dụng mô hình **MVC + Service Layer + Repository Pattern**.

```
Route → Middleware → FormRequest → Controller → Service → Repository → Model
Response ← ApiResource ← Controller ← Service
```

### Nguyên tắc cốt lõi

- **Controller**: Chỉ điều phối — nhận request, gọi service, trả response.
- **Service**: Toàn bộ business logic nằm ở đây.
- **Repository**: Toàn bộ database query nằm ở đây.
- **Model**: Chỉ định nghĩa cấu trúc, relationship, cast — không chứa logic.

---

## 3. CẤU TRÚC THƯ MỤC

```
app/
├── Exceptions/Domain/
│   ├── NotFoundException.php
│   └── BusinessException.php
├── Http/
│   ├── Controllers/Api/V1/        # Versioning bắt buộc
│   ├── Middleware/ForceJsonResponse.php
│   ├── Requests/{Model}/          # StoreRequest, UpdateRequest
│   └── Resources/{Model}/         # ApiResource
├── Models/
├── Repositories/
│   ├── Contracts/                 # Interfaces
│   ├── Eloquent/                  # Implementations
│   └── BaseRepository.php
├── Services/
└── Providers/
    ├── AppServiceProvider.php
    └── RepositoryServiceProvider.php

routes/
├── api.php
└── api/v1.php
```

---

## 4. ROUTING

```php
// routes/api.php
Route::prefix('v1')
    ->name('v1.')
    ->middleware(['force.json'])
    ->group(base_path('routes/api/v1.php'));

// routes/api/v1.php
Route::middleware('throttle:auth')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::apiResource('users', UserController::class);
});
```

**Quy tắc:**
- Tất cả route trong `routes/api/v1.php`.
- Dùng `Route::apiResource()` cho CRUD chuẩn.
- Tên route: `v1.users.index`, `v1.users.store`, v.v.
- Không đặt logic xử lý trong route file.

---

## 5. CONTROLLER

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->paginate($request->only(['search', 'per_page']));
        return UserResource::collection($users)->response();
    }

    public function show(int $id): UserResource
    {
        return new UserResource($this->userService->findOrFail($id));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());
        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, int $id): UserResource
    {
        return new UserResource($this->userService->update($id, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->userService->delete($id);
        return response()->json(['message' => 'Xóa thành công.']);
    }
}
```

**Quy tắc:**
- Đặt trong `app/Http/Controllers/Api/V1/`.
- Không chứa business logic, không gọi Model trực tiếp.
- Inject Service qua constructor.
- Luôn dùng `FormRequest` cho request có input.
- Luôn trả `ApiResource`, không trả `array` hay Model thô.

---

## 6. FORM REQUEST (VALIDATION)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone'    => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Tên không được để trống.',
            'email.required'     => 'Email không được để trống.',
            'email.unique'       => 'Email này đã được sử dụng.',
            'password.min'       => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'phone.regex'        => 'Số điện thoại không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên', 'email' => 'email',
            'password' => 'mật khẩu', 'phone' => 'số điện thoại',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email ?? '')),
            'phone' => $this->phone ? preg_replace('/\D/', '', $this->phone) : null,
        ]);
    }
}
```

**Quy tắc:**
- Đặt trong `app/Http/Requests/{Model}/`, tách `StoreRequest` và `UpdateRequest`.
- Không validate trong Controller hay Service.
- Luôn khai báo `messages()` bằng tiếng Việt và `attributes()`.

---

## 7. SERVICE LAYER

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters);
    }

    public function findOrFail(int $id): User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            throw new NotFoundException("Người dùng #{$id} không tồn tại.");
        }

        return $user;
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);
            return $this->userRepository->create($data);
        });
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);

        return DB::transaction(function () use ($user, $data) {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            return $this->userRepository->update($user, $data);
        });
    }

    public function delete(int $id): void
    {
        $user = $this->findOrFail($id);
        $this->userRepository->delete($user);
    }
}
```

**Quy tắc:**
- Chứa toàn bộ business logic.
- Inject Repository qua constructor — không gọi Model trực tiếp.
- Throw exception có nghĩa, không trả `null` khi thất bại.
- Dùng `DB::transaction()` khi có nhiều thao tác ghi liên quan.

---

## 8. REPOSITORY LAYER

```php
// Contracts/UserRepositoryInterface.php
interface UserRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(User $user, array $data): User;
    public function delete(User $user): void;
}

// Eloquent/UserRepository.php
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected const RELATIONS = [];  // khai báo eager loading mặc định
    protected int $perPage = 15;

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->model
            ->with(self::RELATIONS)
            ->when(isset($filters['search']), fn ($q) =>
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%")
            )
            ->latest()
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function findById(int $id): ?User
    {
        return $this->model->with(self::RELATIONS)->find($id);
    }

    // ... create, update, delete
}

// BaseRepository.php
abstract class BaseRepository
{
    public function __construct(protected Model $model) {}

    public function all(): Collection { return $this->model->all(); }
    public function findOrFail(int $id): Model { return $this->model->findOrFail($id); }
}

// RepositoryServiceProvider.php
public function register(): void
{
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    // Thêm bind mới vào đây khi tạo Repository mới
}
```

**Quy tắc:**
- Mỗi Repository có một Interface trong `Contracts/`, implementation trong `Eloquent/`.
- Bind trong `RepositoryServiceProvider`, đăng ký trong `bootstrap/providers.php`.
- Chỉ chứa database query — không có business logic.
- Tránh N+1 — dùng `with()` và khai báo constant `RELATIONS`.

---

## 9. MODEL

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'role'              => UserRole::class,
        'password'          => 'hashed',
    ];

    public function khuNha(): HasMany
    {
        return $this->hasMany(KhuNha::class);
    }
}
```

**Quy tắc:**
- Khai báo `$fillable` rõ ràng — không dùng `$guarded = []`.
- Khai báo `$casts` cho boolean, datetime, enum.
- Khai báo `$hidden` cho password, token.
- Dùng `SoftDeletes` nếu cần xóa mềm.
- Không chứa business logic.

---

## 10. API RESOURCE

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'role'       => $this->role?->value,
            'role_label' => $this->role?->label(),
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),

            // Relationship — chỉ hiện khi đã được load
            'khu_nha' => KhuNhaResource::collection($this->whenLoaded('khuNha')),
        ];
    }
}
```

**Quy tắc:**
- Luôn dùng `ApiResource` — không trả Model thô.
- Đặt trong `app/Http/Resources/{Model}/`.
- Dùng `whenLoaded()` cho relationship để tránh N+1.
- Không truy vấn DB trong Resource.

**Cấu trúc JSON Response chuẩn:**
```json
{ "data": { "id": 1, "name": "...", "email": "..." } }

{ "data": [...], "meta": { "current_page": 1, "per_page": 15, "total": 100, "last_page": 7 } }

{ "message": "Dữ liệu không hợp lệ.", "errors": { "email": ["..."] } }
```

---

## 11. EXCEPTION HANDLING

```php
// NotFoundException — 404
class NotFoundException extends Exception
{
    public function __construct(string $message = 'Không tìm thấy dữ liệu.')
    {
        parent::__construct($message, 404);
    }
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}

// BusinessException — 422 (hoặc code tùy chỉnh)
class BusinessException extends Exception
{
    public function __construct(string $message, int $code = 422)
    {
        parent::__construct($message, $code);
    }
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->getCode());
    }
}
```

Exception handler toàn cục trong `bootstrap/app.php` → `withExceptions()`:
- `ValidationException` → 422 + `errors`
- `ModelNotFoundException` → 404
- `AuthenticationException` → 401
- `AuthorizationException` → 403
- `Throwable` trên production → 500 ẩn chi tiết

---

## 12. AUTHENTICATION (Sanctum)

```php
// AuthService
public function login(array $credentials): array
{
    if (!Auth::attempt($credentials)) {
        throw new BusinessException('Email hoặc mật khẩu không đúng.', 401);
    }

    $user = Auth::user();

    if (!$user->is_active) {
        Auth::logout();
        throw new BusinessException('Tài khoản đã bị vô hiệu hóa.', 403);
    }

    $user->tokens()->where('name', 'api_token')->delete();

    $token = $user->createToken('api_token', expiresAt: now()->addDays(30));

    return [
        'user'         => $user,
        'access_token' => $token->plainTextToken,
        'token_type'   => 'Bearer',
        'expires_at'   => $token->accessToken->expires_at?->toISOString(),
    ];
}
```

**Quy tắc:**
- Token phải có tên và `expiresAt`.
- Xóa token cũ cùng tên khi đăng nhập mới.
- Route cần auth thêm middleware `auth:sanctum`.

---

## 13. MIGRATION & DATABASE

```php
Schema::create('ten_bang', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->string('ten');                           // tên ngắn gọn
    $table->boolean('is_active')->default(true)->index();
    $table->softDeletes();                           // nếu cần xóa mềm
    $table->timestamps();
});
```

**Quy tắc:**
- Khai báo FK constraint rõ ràng với `onDelete`.
- Luôn có `timestamps()`, thêm `softDeletes()` nếu cần.
- Tạo index cho cột dùng trong `WHERE` / filter thường xuyên.
- Luôn viết `down()` đảo ngược đúng `up()`.
- Comment cho cột nếu tên không tự giải thích được.

---

## 14. ENUM (PHP 8.1+)

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin     = 'admin';
    case ChuTro    = 'chu_tro';
    case NguoiThue = 'nguoi_thue';

    public function label(): string
    {
        return match($this) {
            self::Admin     => 'Quản trị viên',
            self::ChuTro    => 'Chủ trọ',
            self::NguoiThue => 'Người thuê',
        };
    }
}
```

**Quy tắc:**
- Dùng `BackedEnum` (string) cho giá trị cố định.
- Đặt trong `app/Enums/`.
- Khai báo trong `$casts` của Model.

---

## 15. CODE STYLE

- Luôn có `declare(strict_types=1);` ở đầu mỗi file PHP.
- Luôn khai báo return type cho method (kể cả `: void`).
- Dùng `readonly` cho constructor property khi không cần thay đổi.
- Tối đa **1 level** nested condition — dùng **Early Return**.
- Không có `dd()`, `dump()`, `var_dump()` trong code.

```php
// ✅ Early Return
public function process(BanGhiThue $ban): void
{
    if (!$ban->isDangThue()) {
        throw new BusinessException('Chỉ xử lý bản ghi đang thuê.');
    }

    $this->doSomething($ban);
}
```

---

## 16. SECURITY

- Không bao giờ trust input từ user — luôn validate qua FormRequest.
- Dùng `$fillable` thay `$guarded = []` để tránh mass assignment.
- Không dùng raw query với user input — luôn dùng parameter binding.
- Rate limit public route: đặt `throttle:auth` cho login/register.
- Không log password, token, thông tin nhạy cảm.
- Sensitive config phải đặt trong `.env`, không hard-code.

---

## 17. CHECKLIST KHI TẠO FEATURE MỚI

```
Bước    Layer               File cần tạo
──────────────────────────────────────────────────────────────
1.      Migration       →   database/migrations/
2.      Enum (nếu có)   →   app/Enums/
3.      Model           →   app/Models/
4.      Repository      →   app/Repositories/Contracts/...Interface.php
                            app/Repositories/Eloquent/...Repository.php
                            (Bind trong RepositoryServiceProvider)
5.      Service         →   app/Services/...Service.php
6.      FormRequest     →   app/Http/Requests/{Model}/Store...Request.php
                            app/Http/Requests/{Model}/Update...Request.php
7.      Resource        →   app/Http/Resources/{Model}/...Resource.php
8.      Controller      →   app/Http/Controllers/Api/V1/...Controller.php
9.      Route           →   routes/api/v1.php
```
