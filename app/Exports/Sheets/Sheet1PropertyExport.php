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

class Sheet1PropertyExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Sheet1';
    }

    public function collection(): Collection
    {
        return collect([
            ['📌 HƯỚNG DẪN SHEET 1: Khai báo danh sách Khu nhà. MỖI KHU NHÀ CHỈ NHẬP ĐÚNG 1 DÒNG DUY NHẤT.'],
            ['Mã khu nhà (*)', 'Tên khu nhà (*)', 'Loại nhà (*)', 'Trạng thái nhà', 'Địa chỉ (*)', 'Số tầng', 'Số phòng dự kiến', 'Người quản lý', 'Mô tả khu'],
            ['KH-01', 'Khu trọ Cao Lỗ', 'Phòng trọ', 'Hoạt động', '180 Cao Lỗ, Quận 8', 3, 15, 'Nguyễn Duy Hùng', 'Khu an ninh'],
            ['KH-02', 'Căn hộ Mini Q7', 'Căn hộ', 'Hoạt động', 'Số 10 Nguyễn Thị Thập, Q7', 5, 20, 'Trần Văn A', 'Khu cao cấp']
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:I1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1')->getFont()->setBold(true)->getColor()->setARGB('C00000');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getStyle('A2:I2')->getFont()->setBold(true);
        $sheet->getStyle('A2:I2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DCE6F1');
        $sheet->getStyle('A1:I4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $createDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi nhập liệu')->setError('Vui lòng CHỌN từ danh sách!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                $sheet->setDataValidation("C3:C1000", $createDropdown('Phòng trọ,Căn hộ,Homestay,Nhà nguyên căn'));
                $sheet->setDataValidation("D3:D1000", $createDropdown('Hoạt động,Ngừng hoạt động'));
            },
        ];
    }
}
