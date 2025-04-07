<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    // Khóa chính vẫn là 'id' và tự tăng (trừ khi bạn thay đổi)
    // protected $primaryKey = 'id';
    // public $incrementing = true;
    // protected $keyType = 'int';

    /**
     * Fillable - Khớp với cột DB (trừ id, timestamps).
     * KHÔNG có 'details'.
     */
    protected $fillable = [
        'userId',       // Người báo cáo
        'reportedBy',   // Người bị báo cáo
        'reason',
        'status',
    ];

    /**
     * Casting (nếu cần).
     */
    protected $casts = [];

    /**
     * Quan hệ: Lấy người dùng đã TẠO báo cáo này.
     * Đặt tên là user() và dùng foreign key 'userId'.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Quan hệ: Lấy người dùng đã BỊ báo cáo trong báo cáo này.
     * Đặt tên là reportedByUser() và dùng foreign key 'reportedBy'.
     */
    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportedBy');
    }
}