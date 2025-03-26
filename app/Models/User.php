<?php

namespace App\Models;

use App\Models\Skill;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'password',
        'role',
        'photo',
        'active',
        'lock',
        'passwordResetToken',
        'passwordResetExpires',
        'emailVerificationToken',
        'emailVerificationExpires',
        'passwordChangedAt',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token', // Thêm remember_token
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Sử dụng 'hashed' để tự động băm mật khẩu
        'passwordResetExpires' => 'datetime',
        'emailVerificationExpires' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skills', 'userId', 'skillId');
    }
    public function comparePassword($password)
    {
        return Hash::check($password, $this->password);
    }

    public function createEmailVerificationToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->email_verification_token = hash('sha256', $token);
        $this->email_verification_expires = now()->addHours(24);
        return $token;
    }

    public function createPasswordResetToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->password_reset_token = hash('sha256', $token);
        $this->password_reset_expires = now()->addMinutes(10);
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
