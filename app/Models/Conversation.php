<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'initiator_id',
        'receiver_id',
        'last_message_at',
        'initiator_type',
        'receiver_type',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get all messages for this conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the initiator of the conversation
     */
    public function initiator()
    {
        return $this->initiator_type === 'user' 
            ? $this->belongsTo(UUser::class, 'initiator_id')
            : $this->belongsTo(SuperAdmin::class, 'initiator_id');
    }

    /**
     * Get the receiver of the conversation
     */
    public function receiver()
    {
        return $this->receiver_type === 'user' 
            ? $this->belongsTo(UUser::class, 'receiver_id')
            : $this->belongsTo(SuperAdmin::class, 'receiver_id');
    }

    /**
     * Get the last message in the conversation
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * Scope a query to only include conversations for a specific user
     */
    public function scopeForUser($query, $userId, $userType = 'user')
    {
        return $query->where(function($q) use ($userId, $userType) {
            $q->where('initiator_id', $userId)
              ->where('initiator_type', $userType)
              ->orWhere(function($q2) use ($userId, $userType) {
                  $q2->where('receiver_id', $userId)
                     ->where('receiver_type', $userType);
              });
        });
    }

    /**
     * Get unread messages count for a user
     */
    public function unreadMessagesCount($userId, $userType = 'user')
    {
        return $this->messages()
            ->where('is_read', false)
            ->where('sender_id', '!=', $userId)
            ->where('sender_type', '!=', $userType)
            ->count();
    }
}