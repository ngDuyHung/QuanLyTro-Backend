<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\Sheet1PropertyExport;
use App\Exports\Sheets\Sheet2RoomExport;
use App\Exports\Sheets\Sheet3ServiceExport;
use App\Exports\Sheets\Sheet4LeaseTenantExport;
use App\Exports\Sheets\Sheet5LeaseServiceExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterDataTemplateExport implements WithMultipleSheets
{
    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function sheets(): array
    {
        return [
            new Sheet1PropertyExport($this->userId),
            new Sheet2RoomExport($this->userId),
            new Sheet3ServiceExport($this->userId),
            new Sheet4LeaseTenantExport($this->userId),
            new Sheet5LeaseServiceExport($this->userId),
        ];
    }
}
