<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    protected static function boot()
    {
        parent::boot();

        // Clear cache when rate is created, updated, or deleted
        static::created(function ($rate) {
            $ratedUser = User::find($rate->rated_user_id);
            if ($ratedUser) {
                $ratedUser->clearCachedStats();
            }
        });

        static::updated(function ($rate) {
            $ratedUser = User::find($rate->rated_user_id);
            if ($ratedUser) {
                $ratedUser->clearCachedStats();
            }
        });

        static::deleted(function ($rate) {
            $ratedUser = User::find($rate->rated_user_id);
            if ($ratedUser) {
                $ratedUser->clearCachedStats();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'rated_user_id',
        'conversation_id',
        'rate'
    ];

    protected $casts = [
        'rate' => 'integer',
    ];

    /**
     * The user who gave the rating (asker/starter)
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who was rated (answerer/recipient)
     */
    public function ratedUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    /**
     * The conversation that was rated
     */
    public function conversation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
