<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// use Illuminate\Support\Facades\Schema; // Bỏ comment nếu bạn cần dùng Schema facade (ví dụ: cho defaultStringLength)

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Nơi để đăng ký các ràng buộc trong service container.
     * Ví dụ: $this->app->bind(MyServiceInterface::class, MyServiceImplementation::class);
     */
    public function register(): void
    {
        // Thường để trống trong các ứng dụng đơn giản ban đầu
    }

    /**
     * Bootstrap any application services.
     *
     * Nơi để thực hiện các tác vụ khởi động sau khi tất cả các provider khác đã được đăng ký.
     * Ví dụ: lắng nghe sự kiện, đăng ký các macro, thiết lập cấu hình mặc định.
     */
    public function boot(): void
    {
        // KHÔNG gọi $this->registerPolicies(); ở đây.

        // Ví dụ: Nếu dùng MySQL phiên bản cũ hơn 5.7.7 hoặc MariaDB cũ hơn 10.2.2,
        // bạn có thể cần đặt độ dài mặc định cho string để tránh lỗi khi migrate:
        // \Illuminate\Support\Facades\Schema::defaultStringLength(191);
    }
}