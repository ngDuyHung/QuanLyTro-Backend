<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\Lease;
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

class Sheet4LeaseTenantExport implements FromCollection, WithStyles, ShouldAutoSize, WithEvents, WithTitle
{
    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function title(): string
    {
        return 'Sheet4';
    }

    public function collection(): Collection
    {
        // 1. Khởi tạo Banner Hướng dẫn và cấu trúc 14 tiêu đề cột chuẩn rest
        $data = collect([
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
            ]
        ]);

        // 2. Tải toàn bộ hợp đồng đang hoạt động (active) kèm nạp mượt data quan hệ liên đới
        $leases = Lease::with(['room.property', 'tenant', 'meterReadings'])
            ->whereHas('room.property', function ($query) {
                $query->where('user_id', $this->userId);
            })
            ->where('status', 'active')
            ->get()
            ->sortBy([
                ['room.property.code', 'asc'],
                ['room.name', 'asc']
            ]);

        // 3. Cơ chế Fallback nếu trống trải dữ liệu
        if ($leases->isEmpty()) {
            $data->push([
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
            ]);
        } else {
            // 4. Duyệt vòng lặp bóc tách xuất dữ liệu thật
            foreach ($leases as $lease) {
                // Lấy chỉ số điện nước bàn giao đầu kỳ (Sắp xếp theo ID nhỏ nhất để lấy record khởi tạo ban đầu)
                $elecReading = $lease->meterReadings->where('type', 'electricity')->sortBy('id')->first();
                $waterReading = $lease->meterReadings->where('type', 'water')->sortBy('id')->first();

                $elecStart = $elecReading ? $elecReading->previous_reading : 0;
                $waterStart = $waterReading ? $waterReading->previous_reading : 0;

                // A. Đẩy dòng Khách Đại Diện (Chủ hợp đồng) lên trước
                $data->push([
                    $lease->room->property->code,
                    $lease->room->name,
                    'Đại diện',
                    $lease->tenant->full_name,
                    $lease->tenant->phone,
                    $lease->tenant->id_card_number,
                    $lease->tenant->email,
                    $lease->start_date,
                    $lease->billing_day,
                    $lease->occupants_count,
                    $lease->room_price,
                    $lease->deposit,
                    $elecStart,
                    $waterStart
                ]);

                // B. Tìm kiếm và nạp các thành viên Ở Ghép (members) đang hoạt động cùng phòng này
                $roommates = \App\Models\LeaseMember::with('tenant')
                    ->where('lease_id', $lease->id)
                    ->whereNull('move_out_date') // Những người chưa rời đi
                    ->get();

                foreach ($roommates as $roommate) {
                    if (!$roommate->tenant) {
                        continue;
                    }

                    $data->push([
                        $lease->room->property->code,
                        $lease->room->name,
                        'Ở ghép',
                        $roommate->tenant->full_name,
                        $roommate->tenant->phone,
                        $roommate->tenant->id_card_number,
                        $roommate->tenant->email,
                        $roommate->move_in_date ?? $lease->start_date,
                        '', // Bỏ trống công nợ tài chính đơn lẻ của thành viên ở ghép
                        '',
                        '',
                        '',
                        '',
                        ''
                    ]);
                }
            }
        }

        return $data;
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

        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:N{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        // Định dạng text cho SĐT và CCCD để tránh bị Excel rụng mất số 0 ở đầu dòng
        $sheet->getStyle("E3:E{$highestRow}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle("F3:F{$highestRow}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Định dạng phân tách hàng nghìn cho tiền tệ
        $sheet->getStyle("K3:L{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $maxRangeRow = max(1000, $highestRow); // Bảo đảm bao phủ tối thiểu 1000 dòng cho việc nhập thêm dữ liệu

                // 1. CÁC HELPER DROPDOWN DỮ LIỆU ĐỘNG CHÉO SHEETS
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

                // ÁP DỤNG DROPDOWN DATA VALIDATION
                $sheet->setDataValidation("A3:A{$maxRangeRow}", $createDynamicDropdown('Sheet1', 'A'));
                $sheet->setDataValidation("B3:B{$maxRangeRow}", $createDependentDropdown());
                $sheet->setDataValidation("C3:C{$maxRangeRow}", $createStaticDropdown('Đại diện,Ở ghép'));

                // TỰ ĐỘNG BÔI XÁM CÁC Ô KHÔNG CẦN THIẾT NẾU LÀ "Ở GHÉP" (Giữ nguyên kiến trúc thông minh của bạn)
                $condition = new Conditional();
                $condition->setConditionType(Conditional::CONDITION_EXPRESSION);
                $condition->addCondition('=$C3="Ở ghép"');

                $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('FFEFEFEF');
                $condition->getStyle()->getFont()->getColor()->setARGB('FFA6A6A6');

                $conditionalStyles = $sheet->getStyle("I3:N{$maxRangeRow}")->getConditionalStyles();
                $conditionalStyles[] = $condition;
                $sheet->getStyle("I3:N{$maxRangeRow}")->setConditionalStyles($conditionalStyles);
            },
        ];
    }
}
