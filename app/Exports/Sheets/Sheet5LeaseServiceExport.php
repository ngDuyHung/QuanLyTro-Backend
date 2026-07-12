<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\LeaseServiceItem;
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
    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function title(): string
    {
        return 'Sheet5';
    }

    public function collection(): Collection
    {
        // 1. Khởi tạo Banner Hướng dẫn và tiêu đề cột
        $data = collect([
            [
                '📌 HƯỚNG DẪN SHEET 5: Khai báo dịch vụ ĐANG SỬ DỤNG của từng phòng. ' .
                    '⚠️ LƯU Ý: Phải chọn "Mã khu nhà" trước rồi mới chọn "Tên phòng". Nếu cột "Đơn giá riêng" để trống, hệ thống sẽ lấy giá mặc định.'
            ],
            ['Mã khu nhà (*)', 'Tên/Số phòng (*)', 'Loại dịch vụ (*)', 'Số lượng (*)', 'Đơn giá riêng (Tùy chọn)'],
        ]);

        // 2. Truy vấn danh sách dịch vụ của các hợp đồng ĐANG HOẠT ĐỘNG
        $leaseServices = LeaseServiceItem::with(['lease.room.property'])
            ->whereHas('lease', function ($query) {
                $query->where('status', 'active');
            })
            ->whereHas('lease.room.property', function ($query) {
                $query->where('user_id', $this->userId);
            })
            ->get()
            ->sortBy([
                ['lease.room.property.code', 'asc'],
                ['lease.room.name', 'asc']
            ]);

        // 3. Fallback: Nếu không có dữ liệu thật -> Xuất dữ liệu mẫu
        if ($leaseServices->isEmpty()) {
            $data->push(['KH-01', 'P.101', 'Điện', 1, '']);
            $data->push(['KH-01', 'P.101', 'Nước', 2, '']);
            $data->push(['KH-01', 'P.101', 'Rác', 1, 40000]);
        } else {
            // 4. Nếu có dữ liệu: Map dịch vụ và đẩy vào mảng
            $serviceMap = [
                'electricity' => 'Điện',
                'water'       => 'Nước',
                'garbage'     => 'Rác',
                'internet'    => 'Internet',
            ];

            foreach ($leaseServices as $item) {
                // Đề phòng trường hợp dùng Enum Casts
                $rawServiceType = $item->service_type->value ?? $item->service_type;

                if (!array_key_exists($rawServiceType, $serviceMap)) {
                    continue;
                }

                $data->push([
                    $item->lease->room->property->code,
                    $item->lease->room->name,
                    $serviceMap[$rawServiceType],
                    $item->quantity,
                    $item->custom_price, // Chỗ này null Excel sẽ tự hiểu là ô trống
                ]);
            }
        }

        return $data;
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
        
        // Vẽ Border động co giãn
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:E{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        // Định dạng tiền tệ cho Đơn giá riêng
        $sheet->getStyle("E3:E{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
        
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $maxRangeRow = max(1000, $highestRow); // Áp dụng validation tối thiểu 1000 dòng

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

                    // SIÊU CÔNG THỨC MATCH VÀ COUNTIF
                    $validation->setFormula1('=OFFSET(Sheet2!$B$2, IFERROR(MATCH(A3,Sheet2!$A$3:$A$1000,0), 1), 0, MAX(1, COUNTIF(Sheet2!$A$3:$A$1000, A3)), 1)');
                    return $validation;
                };

                // 3. HELPER: Dropdown tĩnh
                $createStaticDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi')->setError('Vui lòng CHỌN từ danh sách xổ xuống!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                // ================= ÁP DỤNG =================
                $sheet->setDataValidation("A3:A{$maxRangeRow}", $createDynamicDropdown('Sheet1', 'A'));
                $sheet->setDataValidation("B3:B{$maxRangeRow}", $createDependentDropdown());
                $sheet->setDataValidation("C3:C{$maxRangeRow}", $createStaticDropdown('Điện,Nước,Rác,Internet'));
            },
        ];
    }
}