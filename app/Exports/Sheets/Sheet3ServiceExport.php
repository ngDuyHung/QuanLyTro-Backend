<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\ServicePrice;
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
    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function title(): string
    {
        return 'Sheet3';
    }

    public function collection(): Collection
    {
        // 1. Khởi tạo Banner Hướng dẫn và Tiêu đề cột
        $data = collect([
            [
                '📌 HƯỚNG DẪN SHEET 3: Cài đặt bảng giá dịch vụ mặc định cho từng Khu nhà. ' .
                    'Mỗi dịch vụ là 1 dòng. Nếu Hợp đồng không nhập giá riêng, hệ thống sẽ lấy giá ở đây.'
            ],
            ['Mã khu nhà (*)', 'Loại dịch vụ (*)', 'Đơn giá (VNĐ) (*)', 'Loại miễn phí', 'Số lượng miễn phí'],
        ]);

        // 2. Lấy danh sách cấu hình giá dịch vụ của các khu nhà thuộc sở hữu của User
        // Sắp xếp theo mã khu nhà để tối ưu hóa hiển thị dữ liệu nhóm trực quan
        $servicePrices = ServicePrice::with('property')
            ->whereHas('property', function ($query) {
                $query->where('user_id', $this->userId);
            })
            ->get()
            ->sortBy('property.code');

        // 3. Nếu chủ nhà CHƯA cài đặt dịch vụ nào -> Xuất dữ liệu mẫu (Fallback)
        if ($servicePrices->isEmpty()) {
            $data->push(['KH-01', 'Điện', 3500, 'Không', 0]);
            $data->push(['KH-01', 'Nước', 15000, 'Theo người', 3]);
            $data->push(['KH-01', 'Rác', 60000, 'Không', 0]);
            $data->push(['KH-01', 'Internet', 100000, 'Không', 0]);
        } else {
            // 4. Nếu ĐÃ CÓ dữ liệu -> Tiến hành dịch ngược Enum sang Tiếng Việt chuẩn chỉnh
            $serviceMap = [
                'electricity' => 'Điện',
                'water'       => 'Nước',
                'garbage'     => 'Rác',
                'internet'    => 'Internet',
            ];

            $freeTypeMap = [
                'none'       => 'Không',
                'per_room'   => 'Theo phòng',
                'per_person' => 'Theo người',
            ];

            foreach ($servicePrices as $price) {
                // Lấy giá trị enum (hỗ trợ cả trường hợp dùng Laravel Casts Enum hoặc String)
                $rawServiceType = (string) $price->service_type->value ?? $price->service_type;
                $rawFreeUnitType = (string) $price->free_unit_type->value ?? $price->free_unit_type;

                // Chỉ xuất các loại dịch vụ nằm trong phạm vi xử lý của hệ thống import (Điện, Nước, Rác, Internet)
                if (!array_key_exists($rawServiceType, $serviceMap)) {
                    continue;
                }

                $data->push([
                    $price->property->code,
                    $serviceMap[$rawServiceType],
                    $price->unit_price,
                    $freeTypeMap[$rawFreeUnitType] ?? 'Không',
                    $price->free_units,
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
        $sheet->getStyle('A2:E2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F8CBAD');
        
        // Tạo khung border động co giãn hoàn hảo theo số dòng dữ liệu thật
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:E{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        // Định dạng tiền tệ cho cột Đơn Giá (Cột C)
        $sheet->getStyle("C3:C{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // HÀM HELPER LẤY DỮ LIỆU ĐỘNG TỪ SHEET 1 (Chặn gõ tay sai mã)
                $createDynamicDropdown = function ($column) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)
                        ->setShowDropDown(true)
                        ->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi nhập liệu')
                        ->setError('Vui lòng CHỌN từ danh sách. Nếu không thấy mã, hãy qua Sheet 1 để nhập trước!');

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

                // Áp dụng Data Validation cho 1000 dòng đầu
                $sheet->setDataValidation("A3:A1000", $createDynamicDropdown('A')); // Cột A: Dò Mã Khu từ Sheet 1
                $sheet->setDataValidation("B3:B1000", $createStaticDropdown('Điện,Nước,Rác,Internet'));
                $sheet->setDataValidation("D3:D1000", $createStaticDropdown('Không,Theo phòng,Theo người'));
            },
        ];
    }
}