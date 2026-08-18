# 🚀 BÍ KÍP TỐI ƯU HÓA HIỆU NĂNG LARAVEL + REACT TRÊN SHARED HOSTING
**Dự án:** Hệ thống quản lý nhà trọ Kiêu Giang  
**Môi trường mục tiêu:** Shared Hosting (1 Core CPU, 1GB RAM, IOPS 1024, I/O 36MB/s)  
**Mục tiêu:** Tốc độ phản hồi API < 200ms, chống tràn RAM, chống nghẽn CPU.

---

## PHẦN 1: TỐI ƯU MÔI TRƯỜNG SERVER (PHP EXTENSIONS)
Để Laravel chạy mượt trên phần cứng yếu, bắt buộc phải "độ" lại môi trường PHP trong cPanel (Select PHP Version).

### ✅ CÁC EXTENSION BẮT BUỘC PHẢI BẬT:
1. **`opcache`**: Trái tim của hiệu năng. Giúp biên dịch sẵn code PHP và lưu trên RAM, giảm CPU từ 100% xuống còn 10-15%.
2. **`apcu` (APC User Cache)**: Cho phép Laravel lưu Cache thẳng vào RAM (Shared Memory) thay vì ghi xuống ổ cứng hay Database.
3. **`igbinary`**: Chuẩn nén dữ liệu thay thế `serialize` mặc định của PHP. Tiết kiệm RAM và giải mã nhanh hơn.
4. **`intl`**: Hỗ trợ các thư viện xuất file PDF, Excel, và format tiền tệ.

### ❌ TUYỆT ĐỐI TRÁNH:
- **`xdebug`**: Nếu bật trên môi trường thực tế (Production), nó sẽ theo dõi từng dòng code, làm tốc độ web chậm đi 10 lần và ngốn cạn 1GB RAM trong tích tắc.

### ⚙️ CẤU HÌNH `.env` (LARAVEL):
```dotenv
APP_ENV=production
APP_DEBUG=false
# Chuyển từ file hoặc database sang APC để dùng RAM làm bộ nhớ đệm

'CACHE_STORE=apap' hoặc 'CACHE_DRIVER=apc' tùy phiên bản ( cái này trên share hosting nên bỏ đi vì vải rest lại cach mỗi lần update code nếu không sẽ vẫn ở code cũ - tắt cả ở exten)



##################################################

PHẦN 2: TỐI ƯU TRUY VẤN CƠ SỞ DỮ LIỆU (ELOQUENT ORM)
Đây là "bệnh lý" phổ biến nhất khiến Shared Hosting bị sập. Cần tuân thủ tuyệt đối các quy tắc sau khi code Backend:

1. Xóa sổ subquery whereHas lồng nhau (Sát thủ IOPS)
❌ Bad Code: (Gây ra hàng ngàn câu query EXISTS chậm chạp)

PHP
Invoice::whereHas('lease.room.property', function ($query) use ($userId) {
    $query->where('user_id',$userId);
})->get();
✅ Good Code: (Lấy mảng ID trước, dùng whereIn trực tiếp)

PHP
$propertyIds = Property::where('user_id',$userId)->pluck('id');
Invoice::whereIn('property_id', $propertyIds)->get();
2. Tránh nhồi dữ liệu thừa vào RAM (Over-fetching)
Chỉ load những relations (bảng liên kết) mà Frontend thực sự cần ở màn hình đó.
❌ Bad Code:

PHP
// Tải hàng loạt chi tiết khoản thu, điện nước vào RAM dù giao diện Table không hiển thị
Invoice::with(['items', 'meterReadings'])->get(); 
✅ Good Code: (Load theo yêu cầu bằng cờ include_details từ Frontend)

PHP
Invoice::query()
    ->when($request->boolean('include_details'), function ($query) {$query->with(['items', 'meterReadings']);
    })
    ->get();
3. Đẩy tính toán xuống Database Engine
Thay vì dùng PHP (RAM) để đếm, tính tổng hay kiểm tra tồn tại, hãy bắt MySQL làm việc đó.

Dùng withCount() thay vì $model->relation->count().

Dùng withSum() thay vì $model->relation->sum('amount').

Dùng withExists() để lấy cờ boolean (True/False) thay vì dùng vòng lặp .some() trên Frontend.

PHẦN 3: TỐI ƯU FRONTEND (REACT SPA)
Lỗi Re-render Layout: Đảm bảo cấu trúc React Router không dùng /* ở route cha khiến Layout bị unmount và mount lại liên tục mỗi khi chuyển tab.

Chặn Spam API: Tránh gọi lại các API dùng chung (như notifications, count-active) mỗi khi chuyển trang. Chỉ fetch lại khi thực sự cần hoặc dùng state management (Zustand/Redux).

Phân trang (Pagination): Luôn giới hạn per_page: 15 hoặc 20 ở các danh sách dữ liệu lớn.

PHẦN 4: QUY TRÌNH DEPLOY VÀ VẬN HÀNH
Khi Cập Nhật Code Lên Hosting:
Vì chúng ta dùng OPcache (lưu code trên RAM), nên khi upload file code mới lên, Hosting có thể không nhận diện ngay. Để ép hệ thống dùng code mới:

Cách 1 (cPanel): Tắt extension opcache rồi bật lại ngay lập tức.

Cách 2 (Terminal/SSH): Chạy lệnh:

Bash
php artisan optimize:clear
Lưu Ý Các Tác Vụ Nặng:
Với 1GB RAM, hãy cẩn thận với 2 tính năng sau (có thể gây CPU spike trong thời gian ngắn):

Xuất PDF: Thư viện tạo PDF ngốn RAM khi compile HTML sang file tĩnh.

AI OCR (Gemini): Bắt buộc phải nén ảnh (max 5MB) trước khi gửi lên Server để phân tích.

***