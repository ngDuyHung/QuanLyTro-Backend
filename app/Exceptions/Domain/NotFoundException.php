<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;

class NotFoundException extends Exception
{
    public function __construct(string $message = 'Không tìm thấy dữ liệu.')
    {
        parent::__construct($message, 404);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
