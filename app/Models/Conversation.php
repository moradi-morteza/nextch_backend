<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasUuids;

    protected $appends = ['starter', 'recipient'];

    // accessors to append starter & recipient data
    public function getStarterAttribute()
    {
        return $this->starter ? $this->starter : null;
    }

    public function getRecipientAttribute()
    {
        return $this->recipient ? $this->recipient : null;
    }

    public function starter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'starter_id');
    }

    public function recipient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
