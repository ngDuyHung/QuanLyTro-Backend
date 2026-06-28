<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Tạo các role mặc định cho hệ thống
        Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'landlord', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tenant',   'guard_name' => 'web']);

        // Tạo tài khoản admin mặc định
        $admin = User::firstOrCreate(
            ['phone' => '0123456789'], // Số điện thoại cố định cho admin
            [
                'name'      => 'Admin',
                'password'  => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // THÊM ĐOẠN NÀY ĐỂ GỌI 2 SEEDER CÒN LẠI
        $this->call([
            SettingSeeder::class,
            ServicePriceSeeder::class,
        ]);
    }
}
