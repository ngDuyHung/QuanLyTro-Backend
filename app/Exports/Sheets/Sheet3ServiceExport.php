<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

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
use Maatwebsite\Excel\Concerns\WithTitle;

class Sheet3ServiceExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Sheet3';
    }
    public function collection(): Collection
    {
        return collect([
            [
                '📌 HƯỚNG DẪN SHEET 3: Cài đặt bảng giá dịch vụ mặc định cho từng Khu nhà. ' .
                    'Mỗi dịch vụ là 1 dòng. Nếu Hợp đồng không nhập giá riêng, hệ thống sẽ lấy giá ở đây.'
            ],
            ['Mã khu nhà (*)', 'Loại dịch vụ (*)', 'Đơn giá (VNĐ) (*)', 'Loại miễn phí', 'Số lượng miễn phí'],
            ['KH-01', 'Điện', 3500, 'Không', 0],
            ['KH-01', 'Nước', 15000, 'Theo người', 3],
            ['KH-01', 'Rác', 60000, 'Không', 0],
            ['KH-01', 'Internet', 100000, 'Không', 0],
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
        $sheet->getStyle('A2:E2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F8CBAD');
        $sheet->getStyle('A1:E6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        $sheet->getStyle('C3:C1000')->getNumberFormat()->setFormatCode('#,##0');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // HÀM HELPER LẤY DỮ LIỆU ĐỘNG TỪ SHEET 1
                $createDynamicDropdown = function ($column) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)
                        ->setShowDropDown(true)
                        ->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi nhập liệu')
                        ->setError('Vui lòng CHỌN từ danh sách. Nếu không thấy mã, hãy qua Sheet 1 để nhập trước!');

                    // CHỐNG SẬP DROPDOWN BẰNG HÀM MAX(1, COUNTA)
                    $validation->setFormula1('=OFFSET(Sheet1!$' . $column . '$3, 0, 0, MAX(1, COUNTA(Sheet1!$' . $column . '$3:$' . $column . '$1000)), 1)');
                    return $validation;
                };

                // HÀM HELPER CHO DỮ LIỆU CỐ ĐỊNH
                $createStaticDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi')->setError('Vui lòng CHỌN từ danh sách xổ xuống!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                // Áp dụng
                $sheet->setDataValidation("A3:A1000", $createDynamicDropdown('A')); // Cột A: Mã Khu
                $sheet->setDataValidation("B3:B1000", $createStaticDropdown('Điện,Nước,Rác,Internet'));
                $sheet->setDataValidation("D3:D1000", $createStaticDropdown('Không,Theo phòng,Theo người'));
            },
        ];
    }
}
