<?php

namespace App\Providers;

use App\Models\Report;
use App\Policies\ReportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Report::class => ReportPolicy::class, // <<< Đảm bảo đã đăng ký
    ];

    public function boot(): void
    {
        $this->registerPolicies(); // <<< Đảm bảo lệnh này được gọi
    }
}