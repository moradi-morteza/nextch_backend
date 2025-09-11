<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'starter_id',
        'recipient_id',
        'status',
        'closed_at'
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Clear cache when conversation status changes to closed
        static::updated(function ($conversation) {
            if ($conversation->status === 'closed' && $conversation->getOriginal('status') !== 'closed') {
                $recipient = User::find($conversation->recipient_id);
                if ($recipient) {
                    $recipient->clearCachedStats();
                }
            }
        });
    }


    public function starter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'starter_id');
    }

    public function recipient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class);
    }
}
