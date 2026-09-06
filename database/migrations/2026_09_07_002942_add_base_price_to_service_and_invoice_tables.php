<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->bigInteger('base_price')->unsigned()->default(0)->after('unit_price')->comment('Phí cố định/tối thiểu (Kịch bản C)');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->bigInteger('base_price_snapshot')->default(0)->after('free_quantity_snapshot')->comment('Lưu vết phí cơ bản/cố định tại thời điểm lập HĐ');
        });
    }

    public function down(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropColumn('base_price');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('base_price_snapshot');
        });
    }
};
