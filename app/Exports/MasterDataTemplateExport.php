<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class MasterDataTemplateExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents
{
    public function collection(): Collection
    {
        return collect([
            // DÒNG 1: BANNER HƯỚNG DẪN TỔNG QUAN
            [
                '📌 HƯỚNG DẪN QUAN TRỌNG: ' .
                    '1. Các cột có dấu (*) là BẮT BUỘC phải điền. ' .
                    '2. Phân vùng màu tiêu đề: Xanh Dương (Khu nhà) | Xanh Lá (Phòng) | Cam (Hợp đồng & Khách). ' .
                    '3. Tại các cột (Chọn ▼), vui lòng chọn từ danh sách thả xuống. ' .
                    '4. Đối với người ở ghép, vui lòng nhập TRÙNG Mã khu nhà và Tên phòng với dòng của người Đại diện, sau đó chọn Vai trò là "Ở ghép".'
            ],

            // DÒNG 2: TIÊU ĐỀ TIẾNG VIỆT MỚI (29 cột: A -> AC)
            [
                // Cột A - I (Khu nhà - 9 cột)
                'Mã khu nhà (*)',
                'Tên khu nhà (*)',
                'Loại nhà (Chọn ▼) (*)',
                'Trạng thái nhà (Chọn ▼) (*)',
                'Địa chỉ chi tiết (*)',
                'Số tầng (*)',
                'Số phòng dự kiến (*)',
                'Tên người quản lý',
                'Mô tả khu nhà',

                // Cột J - S (Phòng - 10 cột)
                'Tên/Số phòng (*)',
                'Giá thuê phòng (*)',
                'Tầng số (*)',
                'Trạng thái phòng (Chọn ▼) (*)',
                'Diện tích (m2)',
                'Số người tối đa',
                'Ngày thu tiền phòng',
                'Cho ở ghép? (Chọn ▼) (*)',
                'Đăng công khai? (Chọn ▼) (*)',
                'Mô tả riêng của phòng',

                // Cột T - AC (Hợp đồng & Khách thuê - 10 cột)
                'Họ tên khách thuê',
                'Số điện thoại khách',
                'Số CCCD/CMND khách',
                'Email khách thuê',
                'Vai trò trong phòng (Chọn ▼) (*)',
                'Ngày bắt đầu HĐ',
                'Ngày thu tiền HĐ',
                'Giá chốt HĐ (*)',
                'Tiền đặt cọc HĐ',
                'Chỉ số ĐIỆN đầu',
                'Chỉ số NƯỚC đầu'
            ],

            // DÒNG 3: DỮ LIỆU MẪU DUY NHẤT (Đầy đủ liên kết)
            [
                'KH-01',
                'Khu trọ Cao Lỗ',
                'Phòng trọ',
                'Hoạt động',
                '180 Cao Lỗ, Phường 4, Quận 8',
                3,
                15,
                'Nguyễn Văn A',
                'Khu trọ an ninh cao',
                'P.101',
                3500000,
                1,
                'Đã cho thuê',
                25,
                3,
                1,
                'Có',
                'Có',
                'Có ban công riêng',
                'Nguyễn Văn Linh',
                '0901234567',
                '079094012345',
                'linh.nguyen@gmail.com',
                'Đại diện',
                '2026-07-01',
                1,
                3500000,
                500000,
                150,
                40
            ]
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        // Dòng 1: Banner Hướng dẫn tổng quan
        $sheet->mergeCells('A1:AD1');
        $sheet->getRowDimension(1)->setRowHeight(40);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('C00000');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A1:AD1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2CC');

        // Dòng 2: Tiêu đề Tiếng Việt
        $sheet->getRowDimension(2)->setRowHeight(28);
        $sheet->getStyle('A2:AD2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2:AD2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Đổ màu phân vùng tiêu đề chính
        $sheet->getStyle('A2:I2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DCE6F1'); // Khu nhà (A-I)
        $sheet->getStyle('J2:S2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2EFDA'); // Phòng (J-S)
        $sheet->getStyle('T2:AD2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FCE4D6'); // Hợp đồng (T-AD)

        // // Highlight màu nền nhạt cảnh báo riêng biệt cho các cột có Dropdown lựa chọn
        // // Cột C (Loại nhà), D (Trạng thái nhà), M (Trạng thái phòng), Q (Cho ở ghép), R (Đăng công khai), X (Vai trò)
        // $dropdownRanges = ['C3:C1000', 'D3:D1000', 'M3:M1000', 'Q3:Q1000', 'R3:R1000', 'X3:X1000'];
        // foreach ($dropdownRanges as $range) {
        //     $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9E6');
        // }

        // Định dạng hiển thị tiền tệ phân tách hàng nghìn cho cột K (Giá phòng) và AA (Tiền đặt cọc)
        $sheet->getStyle('K3:K1000')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('AA3:AA1000')->getNumberFormat()->setFormatCode('#,##0');

        // Khung viền & Ép định dạng Text cho cột SĐT (U) và CCCD (V)
        $sheet->getStyle('A1:AD3')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');
        $sheet->getStyle('U3:V1000')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $maxRow = 1000;

                $createDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Lỗi chọn dữ liệu');
                    $validation->setError('Vui lòng CHỌN một trong các giá trị hợp lệ từ danh sách thả xuống.');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                $createNumericPrompt = function ($title, $message, $isPrice = false) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_WHOLE);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Lỗi định dạng số');
                    $validation->setError($isPrice ? 'Giá tiền phải nhập ký tự số nguyên dương.' : 'Trường này yêu cầu nhập ký tự số.');
                    $validation->setPromptTitle($title);
                    $validation->setPrompt($message);
                    return $validation;
                };

                // Đổ Dropdown Danh sách lựa chọn tiếng Việt
                $sheet->setDataValidation("C3:C{$maxRow}", $createDropdown('Phòng trọ,Căn hộ,Homestay,Nhà nguyên căn'));
                $sheet->setDataValidation("D3:D{$maxRow}", $createDropdown('Hoạt động,Ngừng hoạt động'));
                $sheet->setDataValidation("M3:M{$maxRow}", $createDropdown('Còn trống,Đang bảo trì,Đã cho thuê'));
                $sheet->setDataValidation("Q3:Q{$maxRow}", $createDropdown('Có,Không'));
                $sheet->setDataValidation("R3:R{$maxRow}", $createDropdown('Có,Không'));
                $sheet->setDataValidation("X3:X{$maxRow}", $createDropdown('Đại diện,Ở ghép')); // Dropdown Vai trò mới

                // Cấu hình Tooltip nhắc nhở số
                $pricePrompt = "Vui lòng chỉ gõ số liền mạch (Ví dụ: 3500000).";
                $numberPrompt = "Chỉ nhập chữ số nguyên (Ví dụ: 5).";

                $sheet->setDataValidation("F3:F{$maxRow}", $createNumericPrompt('Nhập số tầng khu nhà', $numberPrompt));
                $sheet->setDataValidation("G3:G{$maxRow}", $createNumericPrompt('Nhập số phòng dự kiến', $numberPrompt));
                $sheet->setDataValidation("K3:K{$maxRow}", $createNumericPrompt('Nhập giá phòng', $pricePrompt, true));
                $sheet->setDataValidation("L3:L{$maxRow}", $createNumericPrompt('Nhập tầng số của phòng', "Tầng số không được lớn hơn số tầng khu nhà ở cột F."));
                $sheet->setDataValidation("N3:N{$maxRow}", $createNumericPrompt('Nhập diện tích', "Chỉ nhập số."));
                $sheet->setDataValidation("O3:O{$maxRow}", $createNumericPrompt('Nhập số người tối đa', $numberPrompt));
                $sheet->setDataValidation("P3:P{$maxRow}", $createNumericPrompt('Nhập ngày thu tiền phòng', "Điền số từ 1 đến 31."));
                $sheet->setDataValidation("Z3:Z{$maxRow}", $createNumericPrompt('Nhập ngày thu tiền hợp đồng', "Điền số từ 1 đến 28."));
                $sheet->setDataValidation("AA3:AA{$maxRow}", $createNumericPrompt('Nhập giá chốt HĐ', $pricePrompt, true)); // MỚI THÊM
                $sheet->setDataValidation("AB3:AB{$maxRow}", $createNumericPrompt('Nhập tiền đặt cọc', $pricePrompt, true));
                $sheet->setDataValidation("AC3:AC{$maxRow}", $createNumericPrompt('Nhập chỉ số điện đầu', $numberPrompt));
                $sheet->setDataValidation("AD3:AD{$maxRow}", $createNumericPrompt('Nhập chỉ số nước đầu', $numberPrompt));
            },
        ];
    }
}
