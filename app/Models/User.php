<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Cache;

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

    protected $appends = ['avatar_url', 'followers_count', 'following_count', 'average_rating', 'closed_conversations_count'];
    
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return getUserAvatarUrl($this->id,$this->avatar);
        }
        return getEmptyAvatarUrl();
    }

    public function getFollowersCountAttribute()
    {
        return Cache::remember("user.{$this->id}.followers_count", 3600, function () {
            return $this->followers()->count();
        });
    }

    public function getFollowingCountAttribute()
    {
        return Cache::remember("user.{$this->id}.following_count", 3600, function () {
            return $this->following()->count();
        });
    }

    public function getAverageRatingAttribute()
    {
        return Cache::remember("user.{$this->id}.average_rating", 3600, function () {
            $averageRating = $this->receivedRates()->avg('rate');
            return $averageRating ? round($averageRating, 1) : 0;
        });
    }

    public function getClosedConversationsCountAttribute()
    {
        return Cache::remember("user.{$this->id}.closed_conversations_count", 3600, function () {
            return $this->answeredConversations()->where('status', 'closed')->count();
        });
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
            $result = $this->following()->attach($userId);
            
            // Clear cache for both users
            $this->clearCachedStats();
            $followedUser = User::find($userId);
            if ($followedUser) {
                $followedUser->clearCachedStats();
            }
            
            return $result;
        }
        return false;
    }

    public function unfollow($userId)
    {
        $result = $this->following()->detach($userId);
        
        // Clear cache for both users
        $this->clearCachedStats();
        $unfollowedUser = User::find($userId);
        if ($unfollowedUser) {
            $unfollowedUser->clearCachedStats();
        }
        
        return $result;
    }

    /**
     * Rates given by this user
     */
    public function givenRates()
    {
        return $this->hasMany(Rate::class, 'user_id');
    }

    /**
     * Rates received by this user
     */
    public function receivedRates()
    {
        return $this->hasMany(Rate::class, 'rated_user_id');
    }

    /**
     * Conversations where this user was the starter (asker)
     */
    public function startedConversations()
    {
        return $this->hasMany(Conversation::class, 'starter_id');
    }

    /**
     * Conversations where this user was the recipient (answerer)
     */
    public function answeredConversations()
    {
        return $this->hasMany(Conversation::class, 'recipient_id');
    }

    /**
     * Clear cached statistics for this user
     */
    public function clearCachedStats()
    {
        Cache::forget("user.{$this->id}.followers_count");
        Cache::forget("user.{$this->id}.following_count");
        Cache::forget("user.{$this->id}.average_rating");
        Cache::forget("user.{$this->id}.closed_conversations_count");
    }

    /**
     * Update cache when user stats change
     */
    public function updateCachedStats()
    {
        $this->clearCachedStats();
        
        // Force recalculation
        $this->followers_count;
        $this->following_count;
        $this->average_rating;
        $this->closed_conversations_count;
    }
}
