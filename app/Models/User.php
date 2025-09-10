<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'email_verified_at',
    ];

    protected static function boot()
    {
        parent::boot();

    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = ['avatar_url', 'followers_count', 'following_count'];
    
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return getUserAvatarUrl($this->id,$this->avatar);
        }
        return getEmptyAvatarUrl();
    }

    public function getFollowersCountAttribute()
    {
        return $this->followers()->count();
    }

    public function getFollowingCountAttribute()
    {
        return $this->following()->count();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    public function isFollowing($userId)
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    public function follow($userId)
    {
        if (!$this->isFollowing($userId) && $this->id !== $userId) {
            return $this->following()->attach($userId);
        }
        return false;
    }

    public function unfollow($userId)
    {
        return $this->following()->detach($userId);
    }
}
