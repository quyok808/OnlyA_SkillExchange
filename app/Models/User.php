<?php

namespace App\Models;

// --- Các use statements hiện có ---
use App\Models\Skill;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

// --- Import các lớp cần thiết cho quan hệ mới ---
use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    // --- Traits, fillable, hidden, casts, keyType, incrementing (Giữ nguyên như code gốc của bạn) ---
    use HasApiTokens, HasFactory, Notifiable, HasUuids;
    protected $fillable = ['name', 'email', 'phone', 'address', 'password', 'role', 'photo', 'active', 'lock', 'passwordResetToken', 'passwordResetExpires', 'emailVerificationToken', 'emailVerificationExpires', 'passwordChangedAt'];
    protected $hidden = ['password', 'remember_token', 'passwordResetToken', 'emailVerificationToken'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'passwordResetExpires' => 'datetime', 'emailVerificationExpires' => 'datetime', 'passwordChangedAt' => 'datetime', 'active' => 'boolean', 'lock' => 'boolean'];
    protected $keyType = 'string';
    public $incrementing = false;

    // =====================================================
    // --- CÁC QUAN HỆ (Relationships) ---
    // =====================================================

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skills', 'userId', 'skillId');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'userId', 'id');
    }


    /**
     * Quan hệ: Lấy tất cả các báo cáo được tạo bởi người dùng này.
     * Sửa foreign key thành 'userId'.
     */
    public function createdReports(): HasMany
    {
        // Liên kết với bảng 'reports' thông qua khóa ngoại 'userId' trên bảng reports
        return $this->hasMany(Report::class, 'userId', 'id'); // <<< SỬA foreign key
    }

    /**
     * Quan hệ: Lấy tất cả các báo cáo nhắm vào người dùng này.
     * Sửa foreign key thành 'reportedBy'.
     */
    public function receivedReports(): HasMany
    {
        // Liên kết với bảng 'reports' thông qua khóa ngoại 'reportedBy' trên bảng reports
        return $this->hasMany(Report::class, 'userId', 'id'); // <<< SỬA foreign key
    }

    // =====================================================
    // --- CÁC PHƯƠNG THỨC KHÁC (Giữ nguyên) ---
    // =====================================================
    public function comparePassword($password)
    {
        return Hash::check($password, $this->password);
    }
    public function createEmailVerificationToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->emailVerificationToken = hash('sha256', $token);
        $this->emailVerificationExpires = now()->addHours(24);
        return $token;
    }

    public function createPasswordResetToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->passwordResetToken = hash('sha256', $token);
        $this->passwordResetExpires = now()->addMinutes(10);
        return $token;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
