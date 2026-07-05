<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Sheets\Sheet1PropertyImport;
use App\Imports\Sheets\Sheet2RoomImport;
use App\Imports\Sheets\Sheet3ServiceImport;
use App\Imports\Sheets\Sheet4LeaseTenantImport;
use App\Imports\Sheets\Sheet5LeaseServiceImport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterDataImport implements WithMultipleSheets
{
    private int $userId;
    private Sheet1PropertyImport $sheet1;
    private Sheet2RoomImport $sheet2;
    private Sheet3ServiceImport $sheet3;
    private Sheet4LeaseTenantImport $sheet4;
    private Sheet5LeaseServiceImport $sheet5;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->sheet1 = new Sheet1PropertyImport($this->userId);
        $this->sheet2 = new Sheet2RoomImport($this->userId);
        $this->sheet3 = new Sheet3ServiceImport($this->userId);
        $this->sheet4 = new Sheet4LeaseTenantImport($this->userId);
        $this->sheet5 = new Sheet5LeaseServiceImport($this->userId);
    }

    public function sheets(): array
    {
        return [
            0 => $this->sheet1,
            1 => $this->sheet2,
            2 => $this->sheet3,
            3 => $this->sheet4,
            4 => $this->sheet5,
        ];
    }

    public function getReport(): array
    {
        return [
            'success_count' => $this->sheet1->getSuccessCount() + $this->sheet2->getSuccessCount() + $this->sheet3->getSuccessCount() + $this->sheet4->getSuccessCount() + $this->sheet5->getSuccessCount(),
            'failed_count'  => $this->sheet1->getFailedCount() + $this->sheet2->getFailedCount() + $this->sheet3->getFailedCount() + $this->sheet4->getFailedCount() + $this->sheet5->getFailedCount(),
            'errors'        => array_merge(
                $this->sheet1->getErrors(),
                $this->sheet2->getErrors(),
                $this->sheet3->getErrors(),
                $this->sheet4->getErrors(),
                $this->sheet5->getErrors()
            ),
        ];
    }
}