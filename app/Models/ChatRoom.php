<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChatRoom extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public function participants()
    {
        return $this->belongsToMany(User::class, 'chat_room_participants', 'chatRoomId', 'userId');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'chatRoomId');
    }
}
