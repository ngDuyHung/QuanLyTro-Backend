<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ServicePriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $services = [
            [
                'property_id' => null, // Null = Áp dụng toàn hệ thống
                'service_type' => 'electricity',
                'unit_price' => 3500, // 3,500đ / kWh
                'free_units' => 0,
                'free_unit_type' => 'none',
                'effective_date' => '2024-01-01',
                'expiry_date' => null,
                'note' => 'Giá điện sinh hoạt mặc định',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'property_id' => null,
                'service_type' => 'water',
                'unit_price' => 20000, // 20,000đ / m3
                'free_units' => 0,
                'free_unit_type' => 'none',
                'effective_date' => '2024-01-01',
                'expiry_date' => null,
                'note' => 'Giá nước sinh hoạt mặc định',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'property_id' => null,
                'service_type' => 'internet',
                'unit_price' => 100000, // 100,000đ / phòng
                'free_units' => 0,
                'free_unit_type' => 'none',
                'effective_date' => '2024-01-01',
                'expiry_date' => null,
                'note' => 'Phí Wifi/Internet mặc định',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'property_id' => null,
                'service_type' => 'garbage',
                'unit_price' => 40000, // 40,000đ / phòng
                'free_units' => 0,
                'free_unit_type' => 'none',
                'effective_date' => '2024-01-01',
                'expiry_date' => null,
                'note' => 'Phí thu gom rác mặc định',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
        ];

        // Xóa dữ liệu cũ nếu chạy lại seeder (Tránh duplicate)
        DB::table('service_prices')->whereNull('property_id')->delete();
        
        // Insert dữ liệu mới
        DB::table('service_prices')->insert($services);
    }
}