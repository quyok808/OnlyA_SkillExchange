<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Skill extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skills', 'skillId', 'userId');
    }
}
