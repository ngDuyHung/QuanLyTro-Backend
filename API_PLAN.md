# KẾ HOẠCH API — QuanLyTro

> Tổng hợp tất cả chức năng API cần triển khai, sắp xếp theo thứ tự ưu tiên.
> Mỗi module liệt kê chi tiết: endpoint, method, mô tả, ghi chú nghiệp vụ.

---

## TỔNG QUAN

| # | Module | Số endpoint | Trạng thái |
|---|--------|-------------|------------|
| 1 | Auth (Xác thực) | 4 | ✅ Đã hoàn thành |
| 2 | Khu Nhà | 5 | ⬜ Chưa làm |
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

## MODULE 2: KHU NHÀ (CRUD)

> Chủ trọ quản lý khu nhà trọ của mình.
> Bảng: `khu_nha` → FK `user_id` (chủ trọ).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/khu-nha` | Danh sách khu nhà của chủ trọ đang đăng nhập (phân trang, search) | Có |
| GET | `/khu-nha/{id}` | Chi tiết khu nhà (kèm thống kê số phòng) | Có |
| POST | `/khu-nha` | Tạo khu nhà mới | Có |
| PUT | `/khu-nha/{id}` | Cập nhật thông tin khu nhà | Có |
| DELETE | `/khu-nha/{id}` | Xóa khu nhà (chỉ khi không còn phòng nào) | Có |

**Nghiệp vụ:**
- Chỉ chủ trọ (`chu_tro`) mới được tạo/sửa/xóa khu nhà.
- Mỗi chủ trọ chỉ thấy khu nhà của mình (`user_id = auth user`).
- Xóa khu nhà: kiểm tra không còn phòng trước khi xóa (FK `restrict` ở phòng → cascade, cần check logic).

---

## MODULE 3: PHÒNG

> Quản lý phòng trong từng khu nhà.
> Bảng: `phong` → FK `khu_nha_id`. UNIQUE(`khu_nha_id`, `ten_phong`).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/khu-nha/{khuNhaId}/phong` | Danh sách phòng trong khu nhà (filter trạng thái, search) | Có |
| GET | `/phong/{id}` | Chi tiết phòng (kèm bản ghi thuê hiện tại nếu có) | Có |
| POST | `/khu-nha/{khuNhaId}/phong` | Tạo phòng mới trong khu nhà | Có |
| PUT | `/phong/{id}` | Cập nhật thông tin phòng | Có |
| DELETE | `/phong/{id}` | Xóa phòng (chỉ khi trạng thái `trong`) | Có |
| PATCH | `/phong/{id}/trang-thai` | Đổi trạng thái phòng (trong/sua_chua) | Có |

**Nghiệp vụ:**
- Tên phòng không được trùng trong cùng 1 khu (UNIQUE constraint).
- Khi **cập nhật giá phòng** (`gia_hien_tai`): tự động tạo bản ghi `lich_su_gia_phong`.
- Trạng thái: `trong` → `dang_thue` (khi tạo hợp đồng), `dang_thue` → `trong` (khi trả phòng).
- Không xóa phòng đang có hợp đồng thuê (`dang_thue`).
- Filter: theo `trang_thai` (trong/dang_thue/sua_chua).

---

## MODULE 4: KHÁCH THUÊ

> Quản lý hồ sơ khách thuê.
> Bảng: `khach_thue`. CCCD unique, có thể liên kết `user_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/khach-thue` | Danh sách khách thuê (search theo tên/SĐT/CCCD, phân trang) | Có |
| GET | `/khach-thue/{id}` | Chi tiết khách thuê (kèm danh sách hợp đồng) | Có |
| POST | `/khach-thue` | Tạo hồ sơ khách thuê mới | Có |
| PUT | `/khach-thue/{id}` | Cập nhật thông tin khách thuê | Có |
| DELETE | `/khach-thue/{id}` | Xóa khách thuê (chỉ khi không có hợp đồng đang thuê) | Có |

**Nghiệp vụ:**
- CCCD không được trùng (unique).
- Chủ trọ quản lý khách thuê liên quan đến khu nhà của mình.
- Không xóa khách thuê đang có hợp đồng `dang_thue`.
- Upload ảnh CCCD trước/sau (URL string).

---

## MODULE 5: BẢN GHI THUÊ (Hợp đồng)

> Cốt lõi hệ thống. Liên kết khách thuê — phòng — hóa đơn.
> Bảng: `ban_ghi_thue` → FK `phong_id`, `khach_thue_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/ban-ghi-thue` | Danh sách hợp đồng (filter: phòng, khách thuê, trạng thái, khu nhà) | Có |
| GET | `/ban-ghi-thue/{id}` | Chi tiết hợp đồng (kèm khách thuê, phòng, thành viên, hóa đơn) | Có |
| POST | `/ban-ghi-thue` | Tạo hợp đồng thuê mới (nhận phòng) | Có |
| PUT | `/ban-ghi-thue/{id}` | Cập nhật hợp đồng (ngày thu tiền, tiền cọc...) | Có |
| PATCH | `/ban-ghi-thue/{id}/tra-phong` | Trả phòng (kết thúc hợp đồng) | Có |
| DELETE | `/ban-ghi-thue/{id}` | Xóa hợp đồng (chỉ vừa tạo, chưa có hóa đơn) | Có |

**Nghiệp vụ:**
- **Tạo hợp đồng**: Phòng phải ở trạng thái `trong` → tự động chuyển phòng sang `dang_thue`.
- **Trả phòng**: Cập nhật `trang_thai = da_tra`, `ngay_ket_thuc = today` → chuyển phòng về `trong`.
- Kiểm tra phòng chỉ có **1 hợp đồng `dang_thue`** tại một thời điểm.
- Ghi chỉ số điện nước đầu vào khi tạo hợp đồng (bản ghi đầu tiên `chi_so_dien_nuoc`).

---

## MODULE 6: THÀNH VIÊN THUÊ

> Quản lý người ở cùng (co-tenants) trong hợp đồng.
> Bảng: `thanh_vien_thue` → FK `ban_ghi_thue_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/ban-ghi-thue/{banGhiThueId}/thanh-vien` | Danh sách thành viên của hợp đồng | Có |
| POST | `/ban-ghi-thue/{banGhiThueId}/thanh-vien` | Thêm thành viên mới | Có |
| PUT | `/thanh-vien-thue/{id}` | Cập nhật thông tin thành viên | Có |
| DELETE | `/thanh-vien-thue/{id}` | Xóa thành viên khỏi hợp đồng | Có |

**Nghiệp vụ:**
- Quan hệ: `vo_chong`, `con`, `cha_me`, `anh_chi_em`, `ban_be`, `khac`.
- Kiểm tra `so_nguoi_toi_da` của phòng (nếu > 0) khi thêm thành viên.
- Có thể set `ngay_ra` khi thành viên rời phòng (không cần xóa).

---

## MODULE 7: DỊCH VỤ & GIÁ

> Quản lý giá dịch vụ (điện, nước, rác, internet).
> Bảng: `dich_vu_gia`. UNIQUE(`khu_nha_id`, `loai_dich_vu`).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/dich-vu-gia` | Danh sách giá dịch vụ mặc định (khu_nha_id = null) | Có |
| GET | `/khu-nha/{khuNhaId}/dich-vu-gia` | Giá dịch vụ áp dụng cho khu nhà (riêng + mặc định fallback) | Có |
| POST | `/dich-vu-gia` | Tạo/cập nhật giá dịch vụ | Có |
| PUT | `/dich-vu-gia/{id}` | Cập nhật đơn giá (tự động ghi `lich_su_gia_dv`) | Có |
| DELETE | `/dich-vu-gia/{id}` | Xóa mức giá dịch vụ | Có |

**Nghiệp vụ:**
- `khu_nha_id = NULL` → giá mặc định toàn hệ thống.
- `khu_nha_id = X` → giá override riêng cho khu X.
- Khi **cập nhật `don_gia`**: tự động tạo bản ghi `lich_su_gia_dv` (ghi lại giá cũ, giá mới, người đổi).
- Loại: `dien`, `nuoc`, `rac`, `internet`.
- Miễn phí: `khong`, `theo_phong`, `theo_nguoi` + `mien_phi_den` (số đơn vị free).

---

## MODULE 8: CHỈ SỐ ĐIỆN NƯỚC

> Ghi nhận chỉ số đồng hồ điện/nước hàng tháng.
> Bảng: `chi_so_dien_nuoc` → FK `ban_ghi_thue_id`, `hoa_don_id` (nullable).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/ban-ghi-thue/{banGhiThueId}/chi-so` | Lịch sử chỉ số điện nước của hợp đồng | Có |
| POST | `/ban-ghi-thue/{banGhiThueId}/chi-so` | Ghi chỉ số mới (điện hoặc nước) | Có |
| PUT | `/chi-so/{id}` | Sửa chỉ số (chỉ khi chưa liên kết hóa đơn) | Có |
| DELETE | `/chi-so/{id}` | Xóa bản ghi chỉ số (chỉ khi chưa liên kết hóa đơn) | Có |

**Nghiệp vụ:**
- `chi_so_cu` = `chi_so_moi` của bản ghi trước (tự động lấy).
- `chi_so_moi` >= `chi_so_cu` (validate).
- Bản ghi đầu tiên (`chi_so_cu = 0`) = chỉ số ban đầu khi ký hợp đồng.
- Không sửa/xóa chỉ số đã liên kết hóa đơn (`hoa_don_id != null`).
- Upload ảnh đồng hồ (`anh_dong_ho` URL).

---

## MODULE 9: HÓA ĐƠN

> Lập & quản lý hóa đơn hàng tháng.
> Bảng: `hoa_don` → FK `ban_ghi_thue_id`. Chi tiết: `chi_tiet_hoa_don`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/hoa-don` | Danh sách hóa đơn (filter: trạng thái, khu nhà, phòng, kỳ) | Có |
| GET | `/hoa-don/{id}` | Chi tiết hóa đơn (kèm chi tiết dòng phí, thanh toán) | Có |
| POST | `/hoa-don` | Tạo hóa đơn mới cho 1 hợp đồng | Có |
| POST | `/hoa-don/hang-loat` | Tạo hóa đơn hàng loạt cho nhiều phòng trong khu nhà | Có |
| PUT | `/hoa-don/{id}` | Cập nhật hóa đơn (chỉ khi chưa thanh toán) | Có |
| DELETE | `/hoa-don/{id}` | Xóa hóa đơn (chỉ khi chưa có thanh toán nào) | Có |

**Nghiệp vụ:**
- Tạo hóa đơn → tự động sinh `chi_tiet_hoa_don`:
  - **Tiền phòng**: `don_gia = gia_hien_tai`, `so_luong = 1`.
  - **Tiền điện**: lấy `chi_so_dien_nuoc` chưa link → tính `(chi_so_moi - chi_so_cu) × đơn giá điện`.
  - **Tiền nước**: tương tự.
  - **Phí rác, internet**: `don_gia × 1` (cố định/tháng).
- Sinh `ma_hoa_don` tự động (VD: `HD-{phong_id}-{yyyyMM}-{random4}`).
- Trạng thái: `chua_thanh_toan` → `thanh_toan_mot_phan` → `da_thanh_toan` (dựa theo tổng thanh toán).
- Liên kết `chi_so_dien_nuoc.hoa_don_id` sau khi tạo.
- **Tạo hàng loạt**: tạo cho tất cả hợp đồng `dang_thue` trong khu nhà, cùng kỳ.

---

## MODULE 10: THANH TOÁN

> Ghi nhận thanh toán cho hóa đơn.
> Bảng: `thanh_toan` → FK `hoa_don_id`, `tai_khoan_id` (nullable).

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/hoa-don/{hoaDonId}/thanh-toan` | Lịch sử thanh toán của hóa đơn | Có |
| POST | `/hoa-don/{hoaDonId}/thanh-toan` | Ghi nhận thanh toán mới | Có |
| PUT | `/thanh-toan/{id}` | Sửa bản ghi thanh toán | Có |
| DELETE | `/thanh-toan/{id}` | Xóa bản ghi thanh toán | Có |

**Nghiệp vụ:**
- Hình thức: `tien_mat` hoặc `chuyen_khoan`.
- `chuyen_khoan` → bắt buộc `tai_khoan_id` (FK tài khoản ngân hàng chủ trọ).
- Sau mỗi lần thanh toán → tự động cập nhật `trang_thai` hóa đơn:
  - Tổng thanh toán >= tổng hóa đơn → `da_thanh_toan`.
  - Tổng thanh toán > 0 nhưng < tổng → `thanh_toan_mot_phan`.
  - Tổng thanh toán = 0 → `chua_thanh_toan`.
- Không cho thanh toán vượt quá tổng hóa đơn.

---

## MODULE 11: TÀI KHOẢN NGÂN HÀNG

> Chủ trọ quản lý tài khoản ngân hàng nhận tiền.
> Bảng: `tai_khoan_ngan_hang` → FK `user_id`.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/tai-khoan-ngan-hang` | Danh sách tài khoản của chủ trọ | Có |
| GET | `/tai-khoan-ngan-hang/{id}` | Chi tiết tài khoản | Có |
| POST | `/tai-khoan-ngan-hang` | Thêm tài khoản ngân hàng mới | Có |
| PUT | `/tai-khoan-ngan-hang/{id}` | Cập nhật tài khoản | Có |
| DELETE | `/tai-khoan-ngan-hang/{id}` | Xóa tài khoản (chỉ khi không phải mặc định hoặc là cái cuối cùng) | Có |

**Nghiệp vụ:**
- `is_default`: Tài khoản nhận tiền mặc định. Mỗi chủ trọ chỉ có 1 tài khoản default.
- Khi set default mới → bỏ default cái cũ.
- `so_tai_khoan` unique toàn hệ thống.
- `ma_ngan_hang` (BIN/short_name) để tích hợp QR Code sau này.

---

## MODULE 12: DASHBOARD & THỐNG KÊ

> Tổng quan cho chủ trọ.

| Method | Endpoint | Mô tả | Auth |
|--------|----------|-------|------|
| GET | `/dashboard/tong-quan` | Thống kê tổng quan (tổng phòng, phòng trống, doanh thu tháng...) | Có |
| GET | `/dashboard/doanh-thu` | Doanh thu theo tháng/khu nhà/phòng (filter theo khoảng thời gian) | Có |
| GET | `/dashboard/hoa-don-qua-han` | Danh sách hóa đơn chưa thanh toán quá hạn | Có |

**Chi tiết `/dashboard/tong-quan`:**
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
  ⬜ Module 2: Khu Nhà
  ⬜ Module 3: Phòng

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
