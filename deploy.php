<?php

namespace Deployer;

require 'recipe/laravel.php';

// 1. Cấu hình kho mã nguồn (Thay link dưới bằng Repo của bạn)
set('repository', 'git@github.com:ngDuyHung/QuanLyTro-Backend.git');
set('keep_releases', 3);
add('shared_dirs', []);
add('shared_files', []);

// 2. Cấu hình VPS
host(getenv('VPS_IP'))
    ->set('remote_user', 'root')
    ->set('deploy_path', '/www/wwwroot/apiv1.duyhung.io.vn');

// 3. Tác vụ phụ: Chạy Seeder theo chuẩn kịch bản cũ
task('artisan:db:seed', function () {
    // Kịch bản cũ gọi lệnh db:seed sau khi migrate
    run('cd {{release_path}} && php artisan db:seed --class=DatabaseSeeder --force');
});

// 4. Tác vụ phụ: Khởi động lại PHP-FPM 8.2
task('reload:php-fpm', function () {
    run('/etc/init.d/php-fpm-82 reload');
});

// 5. Móc nối các luồng (Recipe Laravel đã tự động chạy storage:link, migrate, và optimize cache)
//after('artisan:migrate', 'artisan:db:seed'); // Chạy Seeder ngay sau khi Migrate
after('deploy:symlink', 'reload:php-fpm');
after('deploy:failed', 'deploy:unlock');
