<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\Room;
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
    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function title(): string
    {
        return 'Sheet2';
    }

    public function collection(): Collection
    {
        // 1. Khởi tạo Header và Hướng dẫn
        $data = collect([
            [
                '📌 HƯỚNG DẪN SHEET 2: Khai báo danh sách các Phòng. ' .
                    '⚠️ QUAN TRỌNG: Các phòng thuộc cùng 1 khu nhà bắt buộc phải được nhập LIỀN KỀ NHAU (không nhập xen kẽ).'
            ],
            ['Mã khu nhà (*)', 'Tên/Số phòng (*)', 'Giá thuê phòng (*)', 'Tiền cọc/Thế chân', 'Tầng số', 'Trạng thái phòng (*)', 'Diện tích (m2)', 'Số người tối đa', 'Ngày thu tiền', 'Cho ở ghép? (*)', 'Đăng công khai? (*)', 'Thứ tự'],
        ]);

        // 2. Truy vấn dữ liệu thực tế và lấy kèm Property. 
        // Bắt buộc SortBy property.code để đảm bảo Data Validation (hàm MATCH ở Excel) không bị lỗi.
        $rooms = Room::with('property')
            ->whereHas('property', function ($query) {
                $query->where('user_id', $this->userId);
            })
            ->get()
            ->filter(function ($room) {
                return $room->property !== null; // Loại bỏ các phòng không có khu nhà hợp lệ
            })
            ->sortBy('property.code');

        // 3. Nếu KHÔNG CÓ dữ liệu -> Xuất dữ liệu mẫu (Fallback)
        if ($rooms->isEmpty()) {
            $data->push(['KH-01', 'P.101', 3500000, 100000, 1, 'Còn trống', 25, 5, 12, 'Có', 'Có', 0]);
            $data->push(['KH-01', 'P.102', 3500000, 100000, 1, 'Còn trống', 25, 5, 12, 'Có', 'Có', 1]);
            $data->push(['KH-02', 'CH-01', 5000000, 100000, 1, 'Đã cho thuê', 40, 4, 5, 'Không', 'Có', 2]);
        } else {
            // 4. Nếu CÓ dữ liệu -> Map và Dịch ngược Enum
            $statusMap = [
                'available'   => 'Còn trống',
                'maintenance' => 'Đang bảo trì',
                'occupied'    => 'Đã cho thuê',
                'reserved'    => 'Đã đặt cọc'
            ];

            foreach ($rooms as $room) {
                // Ép kiểu status
                $statusValue = ($room->status instanceof \BackedEnum) ? $room->status->value : (string)$room->status;

                // Logic mới: Nếu là 0 thì hiện "Trệt", nếu null hoặc khác thì hiện số
                $floorDisplay = ($room->floor_number === 0) ? 'Trệt' : (string)$room->floor_number;

                $data->push([
                    $room->property->code,
                    $room->name,
                    (int)$room->current_price,
                    (int)($room->deposit_amount ?? 100000), // Tiền cọc/Thế chân
                    $floorDisplay, // Trả về "Trệt" hoặc "1", "2"...
                    $statusMap[$statusValue] ?? 'Còn trống',
                    (float)($room->area ?? 0),
                    (int)($room->max_occupants ?? 0),
                    (int)($room->billing_day ?? 1),
                    $room->allow_shared ? 'Có' : 'Không',
                    $room->is_public ? 'Có' : 'Không',
                    (int)($room->sort_order ?? 0),
                ]);
            }
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:L1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1')->getFont()->setBold(true)->getColor()->setARGB('C00000');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getStyle('A2:L2')->getFont()->setBold(true);
        $sheet->getStyle('A2:L2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2EFDA');

        // Vẽ Border động tùy theo số lượng dòng thực tế
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:L{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        // Đảm bảo cột Giá tiền (Cột C) có phân tách hàng nghìn
        $sheet->getStyle("C3:C{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("D3:D{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $createDynamicDropdown = function ($targetSheet, $column) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                        ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Lỗi nhập liệu')
                        ->setError("Vui lòng CHỌN từ danh sách. Nếu không thấy mã, hãy qua {$targetSheet} để nhập trước!");

                    $validation->setFormula1('=OFFSET(' . $targetSheet . '!$' . $column . '$3, 0, 0, MAX(1, COUNTA(' . $targetSheet . '!$' . $column . '$3:$' . $column . '$1000)), 1)');
                    return $validation;
                };

                $createStaticDropdown = function ($options) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setErrorTitle('Lỗi')->setError('Vui lòng CHỌN từ danh sách xổ xuống, không gõ tay!');
                    $validation->setFormula1('"' . $options . '"');
                    return $validation;
                };

                $sheet->setDataValidation("A3:A1000", $createDynamicDropdown('Sheet1', 'A'));
                $sheet->setDataValidation("F3:F1000", $createStaticDropdown('Còn trống,Đang bảo trì,Đã cho thuê,Đã đặt cọc'));
                $sheet->setDataValidation("J3:J1000", $createStaticDropdown('Có,Không'));
                $sheet->setDataValidation("K3:K1000", $createStaticDropdown('Có,Không'));
            },
        ];
    }
}
