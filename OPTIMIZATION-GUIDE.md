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

'CACHE_STORE=apap' hoặc 'CACHE_DRIVER=apc' tùy phiên bản