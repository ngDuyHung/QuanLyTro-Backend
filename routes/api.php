<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tất cả API route được tổ chức theo version.
| Mỗi version được group riêng với prefix và middleware tương ứng.
|
*/

Route::prefix('v1')
    ->name('v1.')
    ->middleware(['force.json'])
    ->group(base_path('routes/api/v1.php'));
