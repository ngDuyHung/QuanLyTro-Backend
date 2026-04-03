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

Áp dụng mô hình **Lean MVC** — chuẩn Laravel gốc, đủ dùng cho đồ án, dễ giải thích cho hội đồng.

```
Route → Middleware → FormRequest → Controller → Model (Eloquent)
Response ← ApiResource ← Controller
```

Với module có nghiệp vụ phức tạp (Auth, Hóa đơn, Thanh toán...) mới bổ sung Service:
- và các func nên chủ thích tiếng việt để biết function đó thực hiện chức năng gì
```
Route → Middleware → FormRequest → Controller → Service → Model (Eloquent)
Response ← ApiResource ← Controller ← Service
```

### Nguyên tắc cốt lõi

- **Controller**: Gọi Eloquent trực tiếp cho CRUD đơn giản. Chỉ tạo Service khi controller vượt ~150 dòng hoặc cần dùng lại logic ở nhiều chỗ.
- **Service**: CHỈ dùng cho nghiệp vụ thực sự phức tạp: Auth, tính hóa đơn, xử lý thanh toán, trả phòng tổng hợp...
- **Model**: Định nghĩa `$fillable`, `$casts`, relationship — không chứa logic xử lý.
- **Repository Pattern**: **KHÔNG ÁP DỤNG** — Eloquent ORM đã đủ mạnh. Bọc thêm Repository chỉ mất thời gian mà không mang lại giá trị thực cho đồ án.

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
├── Services/                      # Chỉ tạo khi logic phức tạp
└── Providers/
    └── AppServiceProvider.php

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

    Route::apiResource('properties', PropertyController::class);
});
```

**Quy tắc:**
- Tất cả route trong `routes/api/v1.php`.
- Dùng `Route::apiResource()` cho CRUD chuẩn.
- Tên route: `v1.properties.index`, `v1.properties.store`, v.v.
- Tên bảng, cột, route dùng tiếng Anh. Chỉ comment/label/message bằng tiếng Việt.
- Không đặt logic xử lý trong route file.

---

## 5. CONTROLLER

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $properties = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->when($request->search, fn ($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('address', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PropertyResource::collection($properties)->response();
    }

    public function show(Request $request, int $id): PropertyResource
    {
        $property = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->findOrFail($id);

        return new PropertyResource($property);
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = Property::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $property->loadCount('rooms');

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    public function update(UpdatePropertyRequest $request, int $id): PropertyResource
    {
        $property = Property::where('user_id', $request->user()->id)->findOrFail($id);

        $property->update($request->validated());
        $property->loadCount('rooms');

        return new PropertyResource($property);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $property = Property::where('user_id', $request->user()->id)->findOrFail($id);

        if ($property->rooms()->exists()) {
            throw new BusinessException('Không thể xóa khu nhà vì vẫn còn phòng bên trong.');
        }

        $property->delete();

        return response()->json(['message' => 'Xóa khu nhà thành công.']);
    }
}
```

**Quy tắc:**
- Đặt trong `app/Http/Controllers/Api/V1/`.
- CRUD đơn giản: gọi Eloquent trực tiếp, không cần Service.
- Phức tạp: inject Service qua constructor, controller chỉ điều phối.
- Luôn dùng `FormRequest` cho request có input.
- Luôn trả `ApiResource`, không trả `array` hay Model thô.
- Ownership check: dùng `where('user_id', $request->user()->id)` trước `findOrFail()` — non-owner nhận 404.

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

> **Khi nào tạo Service?** Chỉ khi logic quá phức tạp để để trong Controller: nhiều bước, đụng nhiều model, hoặc cần tái sử dụng ở nơi khác. CRUD đơn giản 1 model → để trong Controller, KHÔNG cần Service.

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// ✅ AuthService xứng đáng có Service: logic phức tạp (token, active check...)
class AuthService
{
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

    public function register(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        return User::create($data);
    }
}
```

**Quy tắc:**
- Chỉ tạo Service khi **thực sự cần**: Auth, tính hóa đơn điện nước, xử lý thanh toán, trả phòng tổng hợp...
- Gọi Eloquent/Model trực tiếp — KHÔNG qua Repository.
- Throw exception có nghĩa, không trả `null` khi thất bại.
- Dùng `DB::transaction()` khi có nhiều thao tác ghi liên quan.

---

## 8. GHI CHÚ: KHÔNG DÙNG REPOSITORY PATTERN

Repository Pattern thêm 2–3 file mỗi module (Interface + Implementation + ServiceProvider binding), tạo boilerplate không cần thiết khi Eloquent ORM đã xử lý hoàn toàn tốt.

**Thay vào đó, xử lý trực tiếp trong Controller:**

```php
// ✅ Eager load — tránh N+1
$rooms = Room::with('property')->where('property_id', $id)->paginate(15);

// ✅ withCount — đếm liên quan không cần thêm query
$properties = Property::withCount('rooms')->where('user_id', $userId)->latest()->paginate(15);

// ✅ Ownership check — dùng where() trước findOrFail()
$property = Property::where('user_id', $request->user()->id)->findOrFail($id);
```

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

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
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
            'properties' => PropertyResource::collection($this->whenLoaded('properties')),
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

**Quy tắc đặt tên:** Tên bảng, cột, enum values dùng **tiếng Anh**. Comment, label, message dùng **tiếng Việt**.

```php
Schema::create('properties', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->string('name');                          // tên bằng tiếng Anh
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
    case Admin    = 'admin';
    case Landlord = 'landlord';
    case Tenant   = 'tenant';

    public function label(): string
    {
        return match($this) {
            self::Admin    => 'Quản trị viên',
            self::Landlord => 'Chủ trọ',
            self::Tenant   => 'Người thuê',
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
public function process(Lease $lease): void
{
    if (!$lease->status->isActive()) {
        throw new BusinessException('Chỉ xử lý hợp đồng đang thuê.');
    }

    $this->doSomething($lease);
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
4.      FormRequest     →   app/Http/Requests/{Model}/Store...Request.php
                            app/Http/Requests/{Model}/Update...Request.php
5.      Resource        →   app/Http/Resources/{Model}/...Resource.php
6.      Controller      →   app/Http/Controllers/Api/V1/...Controller.php
                            (Eloquent trực tiếp — KHÔNG cần Service cho CRUD)
7.      Route           →   routes/api/v1.php

Service (tùy chọn — chỉ khi nghiệp vụ phức tạp):
        Service         →   app/Services/...Service.php
```
