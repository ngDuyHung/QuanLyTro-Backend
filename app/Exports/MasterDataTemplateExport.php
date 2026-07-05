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
    public function sheets(): array
    {
        return [
            new Sheet1PropertyExport(),
            new Sheet2RoomExport(),
            new Sheet3ServiceExport(),
            new Sheet4LeaseTenantExport(),
            new Sheet5LeaseServiceExport(),
        ];
    }
}