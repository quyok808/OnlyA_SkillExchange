<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportPolicy
{
    // Uncomment và sửa logic kiểm tra Admin nếu cần
    // public function before(User $user, string $ability): bool|null
    // {
    //     if ($user->role === 'admin') { return true; }
    //     return null;
    // }

    /** Chỉ Admin xem danh sách */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin'; // <<< SỬA LOGIC ADMIN
    }

    /** Chỉ Admin xem chi tiết */
    public function view(User $user, Report $report): bool
    {
         return $user->role === 'admin'; // <<< SỬA LOGIC ADMIN
    }

    /** Mọi user đăng nhập đều có thể tạo */
    public function create(User $user): bool
    {
        return true;
    }

    /** Chỉ Admin được cập nhật */
    public function update(User $user, Report $report): bool
    {
        return $user->role === 'admin'; // <<< SỬA LOGIC ADMIN
    }

    /** Chỉ Admin được xóa */
    public function delete(User $user, Report $report): bool
    {
        return $user->role === 'admin'; // <<< SỬA LOGIC ADMIN
    }
}