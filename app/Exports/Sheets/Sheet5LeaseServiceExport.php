<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class Sheet5LeaseServiceExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Sheet5';
    }

    public function collection(): Collection
    {
        return collect([
            [
                '📌 HƯỚNG DẪN SHEET 5: Khai báo dịch vụ ĐANG SỬ DỤNG của từng phòng. ' .
                    '⚠️ LƯU Ý: Phải chọn "Mã khu nhà" trước rồi mới chọn "Tên phòng". Nếu cột "Đơn giá riêng" để trống, hệ thống sẽ lấy giá mặc định.'
            ],
            ['Mã khu nhà (*)', 'Tên/Số phòng (*)', 'Loại dịch vụ (*)', 'Số lượng (*)', 'Đơn giá riêng (Tùy chọn)'],
            ['KH-01', 'P.101', 'Điện', 1, ''],
            ['KH-01', 'P.101', 'Nước', 2, ''],
            ['KH-01', 'P.101', 'Rác', 1, 40000]
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1')->getFont()->setBold(true)->getColor()->setARGB('C00000');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getStyle('A2:E2')->getFont()->setBold(true);
        $sheet->getStyle('A2:E2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');
        $sheet->getStyle('A1:E5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        $sheet->getStyle('E3:E1000')->getNumberFormat()->setFormatCode('#,##0');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. HELPER: Dropdown độc lập (Dành cho Mã Khu)
                $createDynamicDropdown = function ($targetSheet, $column) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi nhập liệu')
                        ->setError("Vui lòng CHỌN từ danh sách. Nếu không thấy mã, hãy qua {$targetSheet} để nhập trước!");

                    $validation->setFormula1('=OFFSET(' . $targetSheet . '!$' . $column . '$3, 0, 0, MAX(1, COUNTA(' . $targetSheet . '!$' . $column . '$3:$' . $column . '$1000)), 1)');
                    return $validation;
                };

                // 2. HELPER: Dropdown PHỤ THUỘC (Dành cho Tên Phòng phụ thuộc vào Mã Khu)
                $createDependentDropdown = function () {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi chọn phòng')
                        ->setError('Vui lòng chọn Mã Khu trước! (Lưu ý: Tại Sheet 2, các phòng cùng 1 khu phải nằm liền kề nhau).');

                    // SIÊU CÔNG THỨC: 
                    // MATCH: Tìm dòng bắt đầu của Mã Khu (Cột A) bên Sheet 2.
                    // COUNTIF: Đếm xem Mã Khu đó có bao nhiêu phòng để quyết định độ dài danh sách xổ xuống.
                    $validation->setFormula1('=OFFSET(Sheet2!$B$2, IFERROR(MATCH(A3,Sheet2!$A$3:$A$1000,0), 1), 0, MAX(1, COUNTIF(Sheet2!$A$3:$A$1000, A3)), 1)');
                    return $validation;
                };

                // 3. HELPER: Dropdown tĩnh (Vai trò, Dịch vụ...)
                $createStaticDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi')->setError('Vui lòng CHỌN từ danh sách xổ xuống!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                // ================= ÁP DỤNG =================
                // Cột A: Mã Khu (Tự động quét Sheet 1)
                $sheet->setDataValidation("A3:A1000", $createDynamicDropdown('Sheet1', 'A'));

                // Cột B: Tên Phòng (Phụ thuộc vào Cột A hiện tại, quét từ Sheet 2)
                $sheet->setDataValidation("B3:B1000", $createDependentDropdown());

                // --- ĐOẠN DƯỚI NÀY TÙY VÀO ĐANG Ở SHEET NÀO ĐỂ DÙNG OPTIONS TƯƠNG ỨNG ---
                $sheet->setDataValidation("C3:C1000", $createStaticDropdown('Điện,Nước,Rác,Internet'));
            },
        ];
    }
}
