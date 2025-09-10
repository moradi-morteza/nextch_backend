<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class UserController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:255',
            'limit' => 'integer|min:1|max:50'
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 10);

        $users = User::where(function (Builder $q) use ($query) {
            $q->where('full_name', 'LIKE', "%{$query}%")
              ->orWhere('username', 'LIKE', "%{$query}%");
        })
        ->where('id', '!=', Auth::id())
        ->select('id', 'full_name', 'username', 'avatar', 'bio')
        ->limit($limit)
        ->get();

        $currentUser = Auth::user();
        $usersWithFollowStatus = $users->map(function ($user) use ($currentUser) {
            $user->is_following = $currentUser->isFollowing($user->id);
            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $usersWithFollowStatus,
            'total' => $usersWithFollowStatus->count()
        ]);
    }

    public function profile(Request $request, $userId = null): JsonResponse
    {
        $targetUserId = $userId ?: Auth::id();
        
        $user = User::select('id', 'full_name', 'username', 'avatar', 'bio', 'created_at')
            ->withCount(['followers', 'following'])
            ->find($targetUserId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $currentUser = Auth::user();
        $user->is_following = $currentUser->isFollowing($user->id);
        $user->is_own_profile = $currentUser->id === $user->id;

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function follow(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        
        if ($currentUser->id === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot follow yourself'
            ], 422);
        }

        $userToFollow = User::find($userId);
        if (!$userToFollow) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($currentUser->isFollowing($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Already following this user'
            ], 422);
        }

        $currentUser->follow($userId);

        return response()->json([
            'success' => true,
            'message' => 'User followed successfully'
        ]);
    }

    public function unfollow(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        
        if ($currentUser->id === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot unfollow yourself'
            ], 422);
        }

        $userToUnfollow = User::find($userId);
        if (!$userToUnfollow) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if (!$currentUser->isFollowing($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Not following this user'
            ], 422);
        }

        $currentUser->unfollow($userId);

        return response()->json([
            'success' => true,
            'message' => 'User unfollowed successfully'
        ]);
    }

    public function followers(Request $request, $userId = null): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:50'
        ]);

        $targetUserId = $userId ?: Auth::id();
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);
        $offset = ($page - 1) * $limit;

        $user = User::find($targetUserId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $followers = $user->followers()
            ->select('users.id', 'users.full_name', 'users.username', 'users.avatar', 'users.bio')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $currentUser = Auth::user();
        $followersWithStatus = $followers->map(function ($follower) use ($currentUser) {
            $follower->is_following = $currentUser->isFollowing($follower->id);
            return $follower;
        });

        $totalFollowers = $user->followers()->count();

        return response()->json([
            'success' => true,
            'data' => $followersWithStatus,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalFollowers,
                'has_more' => ($offset + $limit) < $totalFollowers
            ]
        ]);
    }

    public function following(Request $request, $userId = null): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:50'
        ]);

        $targetUserId = $userId ?: Auth::id();
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);
        $offset = ($page - 1) * $limit;

        $user = User::find($targetUserId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $following = $user->following()
            ->select('users.id', 'users.full_name', 'users.username', 'users.avatar', 'users.bio')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $currentUser = Auth::user();
        $followingWithStatus = $following->map(function ($followedUser) use ($currentUser) {
            $followedUser->is_following = $currentUser->isFollowing($followedUser->id);
            return $followedUser;
        });

        $totalFollowing = $user->following()->count();

        return response()->json([
            'success' => true,
            'data' => $followingWithStatus,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalFollowing,
                'has_more' => ($offset + $limit) < $totalFollowing
            ]
        ]);
    }
}
