<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Đảm bảo cột id là bigIncrements (bao gồm unsigned, auto increment, primary key)
            // Lưu ý: Lệnh change() yêu cầu cài đặt gói 'doctrine/dbal': composer require doctrine/dbal
            $table->id()->change();
            // Hoặc nếu lệnh trên không hoạt động trên phiên bản MySQL/MariaDB của bạn:
            // $table->bigIncrements('id')->change();
        });
    }

    /**
     * Reverse the migrations.
     * (Hàm down có thể phức tạp hơn nếu bạn muốn hoàn tác chính xác)
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Cần định nghĩa lại kiểu dữ liệu cũ nếu muốn rollback
            // Ví dụ: $table->bigInteger('id')->unsigned()->primary()->change(); (Nếu trước đó không tự tăng)
        });
    }
};