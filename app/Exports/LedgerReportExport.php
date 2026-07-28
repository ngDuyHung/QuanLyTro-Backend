<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Carbon;

class LedgerReportExport implements FromArray, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    private array $ledgerData;
    private string $fromDate;
    private string $toDate;
    private string $propertyName;

    public function __construct(array $ledgerData, string $fromDate, string $toDate, string $propertyName)
    {
        $this->ledgerData = $ledgerData;
        $this->fromDate = Carbon::parse($fromDate)->format('d/m/Y');
        $this->toDate = Carbon::parse($toDate)->format('d/m/Y');
        $this->propertyName = $propertyName;
    }

    public function array(): array
    {
        // 1. Tiêu đề báo cáo
        $rows = [
            ['BÁO CÁO SỔ QUỸ (DÒNG TIỀN)'],
            ['Khu nhà: ' . $this->propertyName],
            ['Thời gian: Từ ' . $this->fromDate . ' đến ' . $this->toDate],
            [''], // Dòng trống cách điệu
            // Dòng 5: Header của bảng
            ['STT', 'Mã Giao Dịch', 'Ngày Tháng', 'Nội Dung', 'Phương Thức', 'Thu', 'Chi', 'Tồn Quỹ'],
        ];

        // 2. Dòng Tồn Đầu Kỳ
        $summary = $this->ledgerData['summary'] ?? [];
        $openingBalance = $summary['opening_balance'] ?? 0;
        
        $rows[] = [
            '', '', '', 'TỒN QUỸ ĐẦU KỲ', '', '', '', $openingBalance
        ];

        // 3. Chi tiết các giao dịch trong kỳ
        $transactions = $this->ledgerData['transactions'] ?? [];
        $stt = 1;
        foreach ($transactions as $tx) {
            $description = $tx['description'];
            if (!empty($tx['is_deposit'])) {
                $description .= ' (Cọc)';
            }

            $rows[] = [
                $stt++,
                $tx['code'],
                Carbon::parse($tx['date'])->format('d/m/Y H:i'),
                $description,
                $this->formatMethod($tx['method']),
                $tx['income'] > 0 ? $tx['income'] : '', // Bỏ trống nếu = 0 cho dễ nhìn
                $tx['expense'] > 0 ? $tx['expense'] : '',
                $tx['balance'],
            ];
        }

        // 4. Dòng Tổng kết cuối kỳ
        $rows[] = [
            '', '', '', 'TỔNG CỘNG PHÁT SINH TRONG KỲ', '', 
            $summary['total_income'] ?? 0, 
            $summary['total_expense'] ?? 0, 
            ''
        ];

        // 4.1 Bóc tách rõ phần doanh thu/chi phí thật và phần tiền cọc giữ hộ trong tổng phát sinh trên
        $rows[] = [
            '', '', '', '  Trong đó - Doanh thu / Chi phí thực', '',
            $summary['operating_income'] ?? ($summary['total_income'] ?? 0),
            $summary['operating_expense'] ?? ($summary['total_expense'] ?? 0),
            ''
        ];
        $rows[] = [
            '', '', '', '  Trong đó - Tiền cọc thu mới / hoàn trả', '',
            $summary['deposit_received'] ?? 0,
            $summary['deposit_refunded'] ?? 0,
            ''
        ];

        $rows[] = [
            '', '', '', 'TỒN QUỸ CUỐI KỲ', '', '', '', $summary['closing_balance'] ?? 0
        ];

        return $rows;
    }

    /**
     * Định dạng CSS cho Excel
     */
    public function styles(Worksheet $sheet): array
    {
        // Gộp ô và căn giữa tiêu đề
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('0e8b4d');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header bảng (Dòng 5)
        $sheet->getStyle('A5:H5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
        $sheet->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0e8b4d');
        $sheet->getStyle('A5:H5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $highestRow = $sheet->getHighestRow();
        
        // Kẻ khung (Borders) cho phần bảng (Từ dòng 5 đến hết)
        $sheet->getStyle("A5:H{$highestRow}")->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('BFBFBF');

        // Bôi đậm dòng Tồn đầu kỳ (Dòng 6), Tổng cộng và Tồn cuối kỳ (Dòng cuối).
        // Lưu ý: giữa "Tổng cộng" và "Tồn quỹ cuối kỳ" giờ có thêm 2 dòng bóc tách
        // Doanh thu/Chi phí thực và Cọc thu/hoàn, nên "Tổng cộng" cách dòng cuối 3 dòng.
        $sheet->getStyle("A6:H6")->getFont()->setBold(true);
        $sheet->getStyle("A{$highestRow}:H{$highestRow}")->getFont()->setBold(true);
        $sheet->getStyle("A" . ($highestRow - 3) . ":H" . ($highestRow - 3))->getFont()->setBold(true);
        // 2 dòng bóc tách hiển thị chữ nghiêng, nhạt hơn để phân biệt với dòng tổng
        $sheet->getStyle("A" . ($highestRow - 2) . ":H" . ($highestRow - 1))
              ->getFont()->setItalic(true)->getColor()->setARGB('64748B');

        // Đổ nền cho toàn khối 4 dòng tổng kết (Tổng cộng -> Bóc tách -> Tồn cuối kỳ)
        $sheet->getStyle("A" . ($highestRow - 3) . ":H{$highestRow}")
              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F8FAFC');

        return [];
    }

    /**
     * Format định dạng số tiền (Có dấu phẩy phân cách hàng nghìn)
     */
    public function columnFormats(): array
    {
        return [
            'F' => '#,##0', // Cột Thu
            'G' => '#,##0', // Cột Chi
            'H' => '#,##0', // Cột Tồn quỹ
        ];
    }

    private function formatMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản',
            'sepay' => 'Chuyển khoản (SePay)',
            default => 'Khác',
        };
    }
}