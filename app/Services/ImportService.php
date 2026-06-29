<?php

declare(strict_types=1);

namespace App\Services;

use App\Imports\MasterDataImport;
use App\Exports\MasterDataTemplateExport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ImportService
{
    /**
     * Xử lý import dữ liệu tổng hợp từ file Excel
     */
    public function processMasterData(UploadedFile $file, int $userId): array
    {
        // Khởi tạo thực thể Import
        $import = new MasterDataImport($userId);

        // Chạy tiến trình đọc file của package Maatwebsite Excel
        Excel::import($import, $file);

        // Trả về kết quả báo cáo sau khi import xong
        return $import->getReport();
    }

   
    /**
     * Xuất file template mẫu định dạng xlsx
     */
    public function downloadTemplate()
    {
        // Trả về file download trực tiếp xuống trình duyệt người dùng
        return Excel::download(new MasterDataTemplateExport, 'mau_import_he_thong_phong.xlsx');
    }
}
