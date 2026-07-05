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
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Color;

class Sheet4LeaseTenantExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Sheet4';
    }

    public function collection(): Collection
    {
        return collect([
            [
                '📌 HƯỚNG DẪN SHEET 4: Lưu thông tin Khách và Hợp đồng. ' .
                    '⚠️ LƯU Ý: Bạn BẮT BUỘC phải chọn "Mã khu nhà" trước, thì cột "Tên/Số phòng" mới xổ ra đúng danh sách phòng của khu đó.'
            ],
            [
                'Mã khu nhà (*)',
                'Tên/Số phòng (*)',
                'Vai trò (*)',
                'Họ tên khách (*)',
                'SĐT (*)',
                'CCCD',
                'Email',
                'Ngày bắt đầu HĐ',
                'Ngày thu tiền',
                'Số lượng người (*)',
                'Giá chốt HĐ (*)',
                'Tiền cọc',
                'Số Điện đầu',
                'Số Nước đầu'
            ],
            [
                'KH-01',
                'P.101',
                'Đại diện',
                'Dương Thị Yến Linh',
                '0987667898',
                '082306003801',
                '',
                '2026-07-04',
                12,
                2,
                3500000,
                1000000,
                140,
                250
            ]
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:N1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1')->getFont()->setBold(true)->getColor()->setARGB('C00000');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getStyle('A2:N2')->getFont()->setBold(true);
        $sheet->getStyle('A2:N2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FCE4D6');
        $sheet->getStyle('A1:N3')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        $sheet->getStyle('E3:E1000')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('F3:F1000')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('K3:L1000')->getNumberFormat()->setFormatCode('#,##0');

        return [];
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. CÁC HELPER DROPDOWN (Giữ nguyên như cũ)
                $createDynamicDropdown = function ($targetSheet, $column) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi nhập liệu')
                        ->setError("Vui lòng CHỌN từ danh sách. Nếu không thấy mã, hãy qua {$targetSheet} để nhập trước!");
                    $validation->setFormula1('=OFFSET(' . $targetSheet . '!$' . $column . '$3, 0, 0, MAX(1, COUNTA(' . $targetSheet . '!$' . $column . '$3:$' . $column . '$1000)), 1)');
                    return $validation;
                };

                $createDependentDropdown = function () {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi chọn phòng')
                        ->setError('Vui lòng chọn Mã Khu trước! (Lưu ý: Các phòng cùng 1 khu phải nằm liền kề nhau).');
                    $validation->setFormula1('=OFFSET(Sheet2!$B$2, IFERROR(MATCH(A3,Sheet2!$A$3:$A$1000,0), 1), 0, MAX(1, COUNTIF(Sheet2!$A$3:$A$1000, A3)), 1)');
                    return $validation;
                };

                $createStaticDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi')->setError('Vui lòng CHỌN từ danh sách xổ xuống!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                // ÁP DỤNG DROPDOWN
                $sheet->setDataValidation("A3:A1000", $createDynamicDropdown('Sheet1', 'A'));
                $sheet->setDataValidation("B3:B1000", $createDependentDropdown());
                $sheet->setDataValidation("C3:C1000", $createStaticDropdown('Đại diện,Ở ghép'));

                // =========================================================
                // NÂNG CẤP UX: TỰ ĐỘNG BÔI XÁM CÁC Ô KHÔNG CẦN THIẾT NẾU LÀ "Ở GHÉP"
                // =========================================================
                $condition = new Conditional();
                $condition->setConditionType(Conditional::CONDITION_EXPRESSION);
                // Nếu Cột C (Vai trò) là "Ở ghép"
                $condition->addCondition('=$C3="Ở ghép"');

                // Set màu nền thành xám nhạt và chữ thành màu xám chìm
                $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('FFEFEFEF');
                $condition->getStyle()->getFont()->getColor()->setARGB('FFA6A6A6');

                // Áp dụng điều kiện này cho các cột từ I đến N (Từ Ngày thu tiền đến Số nước đầu)
                $conditionalStyles = $sheet->getStyle('I3:N1000')->getConditionalStyles();
                $conditionalStyles[] = $condition;
                $sheet->getStyle('I3:N1000')->setConditionalStyles($conditionalStyles);
            },
        ];
    }
}
