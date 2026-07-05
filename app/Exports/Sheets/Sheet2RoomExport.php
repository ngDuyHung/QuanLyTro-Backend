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

class Sheet2RoomExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Sheet2';
    }

    public function collection(): Collection
    {
        return collect([
            [
                '📌 HƯỚNG DẪN SHEET 2: Khai báo danh sách các Phòng. ' .
                    '⚠️ QUAN TRỌNG: Các phòng thuộc cùng 1 khu nhà bắt buộc phải được nhập LIỀN KỀ NHAU (không nhập xen kẽ).'
            ],
            ['Mã khu nhà (*)', 'Tên/Số phòng (*)', 'Giá thuê phòng (*)', 'Tầng số', 'Trạng thái phòng (*)', 'Diện tích (m2)', 'Số người tối đa', 'Ngày thu tiền', 'Cho ở ghép? (*)', 'Đăng công khai? (*)'],
            ['KH-01', 'P.101', 3500000, 1, 'Còn trống', 25, 5, 12, 'Có', 'Có'],
            ['KH-01', 'P.102', 3500000, 1, 'Còn trống', 25, 5, 12, 'Có', 'Có'],
            ['KH-02', 'CH-01', 5000000, 1, 'Đã cho thuê', 40, 4, 5, 'Không', 'Có']
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:J1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1')->getFont()->setBold(true)->getColor()->setARGB('C00000');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getStyle('A2:J2')->getFont()->setBold(true);
        $sheet->getStyle('A2:J2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2EFDA');
        $sheet->getStyle('A1:J5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');
        $sheet->getStyle('C3:C1000')->getNumberFormat()->setFormatCode('#,##0');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. HELPER: Dropdown Lấy dữ liệu động từ Sheet khác (CÓ CHẶN GÕ TAY)
                $createDynamicDropdown = function ($targetSheet, $column) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi nhập liệu')
                        ->setError("Vui lòng CHỌN từ danh sách. Nếu không thấy mã, hãy qua {$targetSheet} để nhập trước!");

                    $validation->setFormula1('=OFFSET(' . $targetSheet . '!$' . $column . '$3, 0, 0, MAX(1, COUNTA(' . $targetSheet . '!$' . $column . '$3:$' . $column . '$1000)), 1)');
                    return $validation;
                };

                // 2. HELPER: Dropdown Tĩnh (CÓ CHẶN GÕ TAY)
                $createStaticDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi')->setError('Vui lòng CHỌN từ danh sách xổ xuống, không gõ tay!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                // ===== ÁP DỤNG CHO SHEET 2 (Phòng) =====
                // Nếu bạn đang dán vào Sheet 3 thì sửa lại các cột áp dụng cho phù hợp nhé:
                $sheet->setDataValidation("A3:A1000", $createDynamicDropdown('Sheet1', 'A')); // Mã Khu
                $sheet->setDataValidation("E3:E1000", $createStaticDropdown('Còn trống,Đang bảo trì,Đã cho thuê,Đã đặt cọc'));
                $sheet->setDataValidation("I3:I1000", $createStaticDropdown('Có,Không'));
                $sheet->setDataValidation("J3:J1000", $createStaticDropdown('Có,Không'));
            },
        ];
    }
}
