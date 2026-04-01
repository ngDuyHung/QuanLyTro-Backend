# KẾ HOẠCH API — QuanLyTro

> Tổng hợp tất cả chức năng API cần triển khai, sắp xếp theo thứ tự ưu tiên.
> Mỗi module liệt kê chi tiết: endpoint, method, mô tả, ghi chú nghiệp vụ.

---

## TỔNG QUAN

| # | Module | Số endpoint | Trạng thái |
|---|--------|-------------|------------|
| 1 | Auth (Xác thực) | 4 | ✅ Đã hoàn thành |
| 2 | Khu Nhà (Properties) | 5 | ✅ Đã hoàn thành |
| 3 | Phòng | 6 | ⬜ Chưa làm |
| 4 | Khách Thuê | 5 | ⬜ Chưa làm |
| 5 | Bản Ghi Thuê (Hợp đồng) | 6 | ⬜ Chưa làm |
| 6 | Thành Viên Thuê | 4 | ⬜ Chưa làm |
| 7 | Dịch Vụ & Giá | 5 | ⬜ Chưa làm |
| 8 | Chỉ Số Điện Nước | 4 | ⬜ Chưa làm |
| 9 | Hóa Đơn | 6 | ⬜ Chưa làm |
| 10 | Thanh Toán | 4 | ⬜ Chưa làm |
| 11 | Tài Khoản Ngân Hàng | 5 | ⬜ Chưa làm |
| 12 | Dashboard & Thống Kê | 3 | ⬜ Chưa làm |
| | **Tổng** | **~57** | |

---

## MODULE 1: AUTH (Xác thực) ✅

> Đã hoàn thành. Sử dụng Laravel Sanctum.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| POST | `/auth/register` | Đăng ký tài khoản chủ trọ | Không |
| POST | `/auth/login` | Đăng nhập, trả token | Không |
| POST | `/auth/logout` | Đăng xuất, xóa token hiện tại | Có |
| GET | `/auth/me` | Lấy thông tin user đang đăng nhập | Có |

---

## MODULE 2: KHU NHÀ (Properties) ✅

> Đã hoàn thành. Chủ trọ quản lý khu nhà trọ của mình.
> Bảng: `properties` → FK `user_id` (chủ trọ).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/properties` | Danh sách khu nhà của chủ trọ đang đăng nhập (phân trang, search) | Có |
| GET | `/properties/{id}` | Chi tiết khu nhà (kèm thống kê số phòng) | Có |
| POST | `/properties` | Tạo khu nhà mới | Có |
| PUT | `/properties/{id}` | Cập nhật thông tin khu nhà | Có |
| DELETE | `/properties/{id}` | Xóa khu nhà (chỉ khi không còn phòng nào) | Có |

**Nghiệp vụ:**
- Chỉ chủ trọ (`landlord`) mới được tạo/sửa/xóa khu nhà.
- Mỗi chủ trọ chỉ thấy khu nhà của mình (`user_id = auth user`).
- Xóa khu nhà: kiểm tra không còn phòng (`rooms`) trước khi xóa.

---

## MODULE 3: PHÒNG (Rooms)

> Quản lý phòng trong từng khu nhà.
> Bảng: `rooms` → FK `property_id`. UNIQUE(`property_id`, `name`).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/properties/{propertyId}/rooms` | Danh sách phòng trong khu nhà (filter status, search) | Có |
| GET | `/rooms/{id}` | Chi tiết phòng (kèm hợp đồng hiện tại nếu có) | Có |
| POST | `/properties/{propertyId}/rooms` | Tạo phòng mới trong khu nhà | Có |
| PUT | `/rooms/{id}` | Cập nhật thông tin phòng | Có |
| DELETE | `/rooms/{id}` | Xóa phòng (chỉ khi `status = available`) | Có |
| PATCH | `/rooms/{id}/status` | Đổi trạng thái phòng (available/maintenance) | Có |

**Nghiệp vụ:**
- Tên phòng không được trùng trong cùng 1 khu (UNIQUE constraint).
- Khi **cập nhật giá phòng** (`current_price`): tự động tạo bản ghi `room_price_histories`.
- Status: `available` → `occupied` (khi tạo hợp đồng), `occupied` → `available` (khi trả phòng).
- Không xóa phòng đang có hợp đồng (`status = occupied`).
- Filter: theo `status` (available/occupied/maintenance).

---

## MODULE 4: KHÁCH THUÊ (Tenants)

> Quản lý hồ sơ khách thuê.
> Bảng: `tenants`. CCCD unique, có thể liên kết `user_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/tenants` | Danh sách khách thuê (search theo tên/SĐT/CCCD, phân trang) | Có |
| GET | `/tenants/{id}` | Chi tiết khách thuê (kèm danh sách hợp đồng) | Có |
| POST | `/tenants` | Tạo hồ sơ khách thuê mới | Có |
| PUT | `/tenants/{id}` | Cập nhật thông tin khách thuê | Có |
| DELETE | `/tenants/{id}` | Xóa khách thuê (chỉ khi không có hợp đồng đang thuê) | Có |

**Nghiệp vụ:**
- CCCD không được trùng (unique).
- Chủ trọ quản lý khách thuê liên quan đến khu nhà của mình.
- Không xóa khách thuê đang có hợp đồng `status = active`.
- Upload ảnh CCCD trước/sau (URL string).

---

## MODULE 5: HỢP ĐỒNG THUÊ (Leases)

> Cốt lõi hệ thống. Liên kết khách thuê — phòng — hóa đơn.
> Bảng: `leases` → FK `room_id`, `tenant_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/leases` | Danh sách hợp đồng (filter: room_id, tenant_id, status, property_id) | Có |
| GET | `/leases/{id}` | Chi tiết hợp đồng (kèm khách thuê, phòng, thành viên, hóa đơn) | Có |
| POST | `/leases` | Tạo hợp đồng thuê mới (nhận phòng) | Có |
| PUT | `/leases/{id}` | Cập nhật hợp đồng (ngày thu tiền, tiền cọc...) | Có |
| PATCH | `/leases/{id}/end` | Trả phòng (kết thúc hợp đồng) | Có |
| DELETE | `/leases/{id}` | Xóa hợp đồng (chỉ vừa tạo, chưa có hóa đơn) | Có |

**Nghiệp vụ:**
- **Tạo hợp đồng**: Phòng phải ở `status = available` → tự động chuyển phòng sang `occupied`.
- **Trả phòng**: Cập nhật `status = ended`, `end_date = today` → chuyển phòng về `available`.
- Kiểm tra phòng chỉ có **1 hợp đồng `status = active`** tại một thời điểm.
- Ghi chỉ số điện nước đầu vào khi tạo hợp đồng (bản ghi đầu tiên `meter_readings`).

---

## MODULE 6: THÀNH VIÊN THUÊ (Lease Members)

> Quản lý người ở cùng (co-tenants) trong hợp đồng.
> Bảng: `lease_members` → FK `lease_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/leases/{leaseId}/members` | Danh sách thành viên của hợp đồng | Có |
| POST | `/leases/{leaseId}/members` | Thêm thành viên mới | Có |
| PUT | `/lease-members/{id}` | Cập nhật thông tin thành viên | Có |
| DELETE | `/lease-members/{id}` | Xóa thành viên khỏi hợp đồng | Có |

**Nghiệp vụ:**
- Quan hệ: `spouse`, `child`, `parent`, `sibling`, `friend`, `other`.
- Kiểm tra `max_occupants` của phòng (nếu > 0) khi thêm thành viên.
- Có thể set `left_at` khi thành viên rời phòng (không cần xóa).

---

## MODULE 7: DỊCH VỤ & GIÁ (Service Prices)

> Quản lý giá dịch vụ (điện, nước, rác, internet).
> Bảng: `service_prices`. UNIQUE(`property_id`, `service_type`).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/service-prices` | Danh sách giá dịch vụ mặc định (property_id = null) | Có |
| GET | `/properties/{propertyId}/service-prices` | Giá dịch vụ áp dụng cho khu nhà (riêng + mặc định fallback) | Có |
| POST | `/service-prices` | Tạo/cập nhật giá dịch vụ | Có |
| PUT | `/service-prices/{id}` | Cập nhật đơn giá (tự động ghi `service_price_histories`) | Có |
| DELETE | `/service-prices/{id}` | Xóa mức giá dịch vụ | Có |

**Nghiệp vụ:**
- `property_id = NULL` → giá mặc định toàn hệ thống.
- `property_id = X` → giá override riêng cho khu X.
- Khi **cập nhật `unit_price`**: tự động tạo bản ghi `service_price_histories` (ghi lại giá cũ, giá mới, người đổi).
- `service_type`: `electricity`, `water`, `waste`, `internet`.
- `free_unit_type`: `none`, `per_room`, `per_person` + `free_units` (số đơn vị miễn phí).

---

## MODULE 8: CHỈ SỐ ĐIỆN NƯỚC (Meter Readings)

> Ghi nhận chỉ số đồng hồ điện/nước hàng tháng.
> Bảng: `meter_readings` → FK `lease_id`, `invoice_id` (nullable).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/leases/{leaseId}/readings` | Lịch sử chỉ số điện nước của hợp đồng | Có |
| POST | `/leases/{leaseId}/readings` | Ghi chỉ số mới (điện hoặc nước) | Có |
| PUT | `/readings/{id}` | Sửa chỉ số (chỉ khi chưa liên kết hóa đơn) | Có |
| DELETE | `/readings/{id}` | Xóa bản ghi chỉ số (chỉ khi chưa liên kết hóa đơn) | Có |

**Nghiệp vụ:**
- `reading_start` = `reading_end` của bản ghi trước (tự động lấy).
- `reading_end` >= `reading_start` (validate).
- Bản ghi đầu tiên (`reading_start = 0`) = chỉ số ban đầu khi ký hợp đồng.
- Không sửa/xóa chỉ số đã liên kết hóa đơn (`invoice_id != null`).
- Upload ảnh đồng hồ (`meter_image` URL).

---

## MODULE 9: HÓA ĐƠN (Invoices)

> Lập & quản lý hóa đơn hàng tháng.
> Bảng: `invoices` → FK `lease_id`. Chi tiết: `invoice_items`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/invoices` | Danh sách hóa đơn (filter: status, property_id, room_id, kỳ) | Có |
| GET | `/invoices/{id}` | Chi tiết hóa đơn (kèm invoice_items, payments) | Có |
| POST | `/invoices` | Tạo hóa đơn mới cho 1 hợp đồng | Có |
| POST | `/invoices/bulk` | Tạo hóa đơn hàng loạt cho nhiều phòng trong khu nhà | Có |
| PUT | `/invoices/{id}` | Cập nhật hóa đơn (chỉ khi chưa thanh toán) | Có |
| DELETE | `/invoices/{id}` | Xóa hóa đơn (chỉ khi chưa có thanh toán nào) | Có |

**Nghiệp vụ:**
- Tạo hóa đơn → tự động sinh `invoice_items`:
  - **Tiền phòng**: `unit_price = current_price`, `quantity = 1`.
  - **Tiền điện**: lấy `meter_readings` chưa link → tính `(reading_end - reading_start) × đơn giá điện`.
  - **Tiền nước**: tương tự.
  - **Phí rác, internet**: `unit_price × 1` (cố định/tháng).
- Sinh `invoice_code` tự động (VD: `INV-{room_id}-{yyyyMM}-{random4}`).
- `status`: `unpaid` → `partial` → `paid` (dựa theo tổng thanh toán).
- Liên kết `meter_readings.invoice_id` sau khi tạo.
- **Tạo hàng loạt** (`/bulk`): tạo cho tất cả hợp đồng `status = active` trong khu nhà, cùng kỳ.

---

## MODULE 10: THANH TOÁN (Payments)

> Ghi nhận thanh toán cho hóa đơn.
> Bảng: `payments` → FK `invoice_id`, `bank_account_id` (nullable).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/invoices/{invoiceId}/payments` | Lịch sử thanh toán của hóa đơn | Có |
| POST | `/invoices/{invoiceId}/payments` | Ghi nhận thanh toán mới | Có |
| PUT | `/payments/{id}` | Sửa bản ghi thanh toán | Có |
| DELETE | `/payments/{id}` | Xóa bản ghi thanh toán | Có |

**Nghiệp vụ:**
- `payment_method`: `cash` hoặc `transfer`.
- `transfer` → bắt buộc `bank_account_id` (FK tài khoản ngân hàng chủ trọ).
- Sau mỗi lần thanh toán → tự động cập nhật `status` hóa đơn:
  - Tổng thanh toán >= tổng hóa đơn → `paid`.
  - Tổng thanh toán > 0 nhưng < tổng → `partial`.
  - Tổng thanh toán = 0 → `unpaid`.
- Không cho thanh toán vượt quá tổng hóa đơn.

---

## MODULE 11: TÀI KHOẢN NGÂN HÀNG (Bank Accounts)

> Chủ trọ quản lý tài khoản ngân hàng nhận tiền.
> Bảng: `bank_accounts` → FK `user_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/bank-accounts` | Danh sách tài khoản của chủ trọ | Có |
| GET | `/bank-accounts/{id}` | Chi tiết tài khoản | Có |
| POST | `/bank-accounts` | Thêm tài khoản ngân hàng mới | Có |
| PUT | `/bank-accounts/{id}` | Cập nhật tài khoản | Có |
| DELETE | `/bank-accounts/{id}` | Xóa tài khoản (chỉ khi không phải mặc định hoặc là cái cuối cùng) | Có |

**Nghiệp vụ:**
- `is_default`: Tài khoản nhận tiền mặc định. Mỗi chủ trọ chỉ có 1 tài khoản default.
- Khi set default mới → bỏ default cái cũ.
- `account_number` unique toàn hệ thống.
- `bank_code` (BIN/short_name) để tích hợp QR Code sau này.

---

## MODULE 12: DASHBOARD & THỐNG KÊ

> Tổng quan cho chủ trọ.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/dashboard/overview` | Thống kê tổng quan (tổng phòng, phòng trống, doanh thu tháng...) | Có |
| GET | `/dashboard/revenue` | Doanh thu theo tháng/khu nhà/phòng (filter theo khoảng thời gian) | Có |
| GET | `/dashboard/overdue-invoices` | Danh sách hóa đơn chưa thanh toán quá hạn | Có |

**Chi tiết `/dashboard/overview`:**
- Tổng số khu nhà
- Tổng số phòng / phòng trống / phòng đang thuê / phòng sửa chữa
- Tổng khách thuê đang ở
- Doanh thu tháng hiện tại (tổng thanh toán)
- Số hóa đơn chưa thanh toán
- Tổng tiền chưa thu

---

## THỨ TỰ TRIỂN KHAI (ĐỀ XUẤT)

```
Phase 1 — Nền tảng (đã xong + cần làm tiếp):
  ✅ Module 1: Auth
  ✅ Module 2: Khu Nhà (Properties)
  ⬜ Module 3: Phòng (Rooms)

Phase 2 — Quản lý thuê:
  ⬜ Module 4: Khách Thuê
  ⬜ Module 5: Bản Ghi Thuê (Hợp đồng)
  ⬜ Module 6: Thành Viên Thuê

Phase 3 — Dịch vụ & Chi phí:
  ⬜ Module 7: Dịch Vụ & Giá
  ⬜ Module 8: Chỉ Số Điện Nước

Phase 4 — Thanh toán:
  ⬜ Module 11: Tài Khoản Ngân Hàng
  ⬜ Module 9: Hóa Đơn
  ⬜ Module 10: Thanh Toán

Phase 5 — Thống kê:
  ⬜ Module 12: Dashboard & Thống Kê
```

---

## GHI CHÚ CHUNG

- Tất cả endpoint đều có prefix: `GET /api/v1/...`
- Auth bằng Bearer Token (Sanctum).
- Phân quyền: Chủ trọ chỉ thao tác trên dữ liệu khu nhà của mình.
- Admin có thể xem tất cả (nếu cần).
- Mọi danh sách đều hỗ trợ phân trang (`per_page`, `page`).
- Search/filter qua query params.
- Response format thống nhất: `{ "data": {...} }` hoặc `{ "data": [...], "meta": {...} }`.
