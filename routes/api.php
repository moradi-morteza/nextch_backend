<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('request_otp', [AuthController::class, 'requestOtp']);
Route::post('verify_otp', [AuthController::class, 'verifyOtp']);

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

# Protected routes
Route::middleware('auth:api')->group(function () {
    Route::get('user', [AuthController::class, 'getUser']);
    Route::post('logout', [AuthController::class, 'logout']);
    
    // User search and profile routes
    Route::get('search/users', [UserController::class, 'search']);
    Route::get('user/profile/{userId?}', [UserController::class, 'profile']);
    
    // Following system
    Route::post('user/{userId}/follow', [UserController::class, 'follow']);
    Route::post('user/{userId}/unfollow', [UserController::class, 'unfollow']);
    
    // Followers and following lists
    Route::get('user/{userId}/followers', [UserController::class, 'followers']);
    Route::get('user/{userId}/following', [UserController::class, 'following']);
    Route::get('user/followers', [UserController::class, 'followers']); // Current user's followers
    Route::get('user/following', [UserController::class, 'following']); // Current user's following
    
    // Rating system
    Route::post('conversation/rate', [UserController::class, 'rate']);
    
    // Conversation and messaging
    Route::post('conversation/message/draft', [ConversationController::class, 'storeDraftMessage']);
    Route::post('conversation/send', [ConversationController::class, 'sendConversation']);
    Route::post('conversation/answer', [ConversationController::class, 'answerConversation']);
    
    // Conversation management
    Route::get('conversations/persons', [ConversationController::class, 'getConversationPersons']);
    Route::get('conversations/person/{personId}', [ConversationController::class, 'getPersonConversations']);
    Route::get('conversation/{conversationId}', [ConversationController::class, 'show']);
});

# telegram mini app
Route::get('/tmaAuthentication', [AuthController::class, 'tmaAuthentication']);
