<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'message',
        'is_read',
        'attachment',
        'attachment_type',
        'attachment_name'
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Get the conversation that the message belongs to
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of the message
     */
    public function sender()
    {
        return $this->sender_type === 'user' 
            ? $this->belongsTo(UUser::class, 'sender_id')
            : $this->belongsTo(SuperAdmin::class, 'sender_id');
    }

    /**
     * Accessor for attachment path
     */
    public function getAttachmentPathAttribute()
    {
        return $this->attachment ? asset('storage/' . $this->attachment) : null;
    }
}