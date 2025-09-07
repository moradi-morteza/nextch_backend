<?php
/**
 * User: Moradi ( @MortezaMoradi )
 * Date: 8/5/2024  Time: 5:59 PM
 */

namespace App\Http\Controllers;

use App\Exceptions\CustomApiException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    const MINI_APP_TOKEN = "7222156665:AAEuRLQ4fZdyH8A_QcSLbcJxWJVHrLCw2HE";
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = User::query()->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        $token->save();

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->token->expires_at->toDateTimeString(),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = request(['email', 'password']);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $request->user();
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        $token->save();

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->token->expires_at->toDateTimeString(),
        ]);
    }

    /**
     * @throws CustomApiException
     */
    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'query' => 'required', // can be phone number or email
        ]);

        // Validate if input is email or phone number
        if (filter_var($data['query'], FILTER_VALIDATE_EMAIL)) {
            $contactType = 'email';
            $contact = $data['query'];
            // Validate email format
        } elseif (preg_match('/^(\+98|98|0)?9\d{9}$/', $data['query'])) {
            $contactType = 'phone';
            $contact = preg_replace('/^0/', '98', $data['query']);
            // Ensure number starts with '98'
            $contact = ltrim($contact, '+');
        } else {
            throw new CustomApiException(__('auth.invalid_phone_number'), 400);
        }

        // Generate OTP and store in cache
        $otp = rand(10000, 99999);
        $expiresAt = Carbon::now()->addMinutes(5);
        Cache::put('otp_' . $contact, $otp, $expiresAt);
        Log::info("OTP generated for $contact : $otp ");
        // Send OTP via SMS or Email
        if ($contactType === 'phone') {
            // TODO: Send OTP via SMS (integrate SMS provider)
        } else {
            // TODO: Send OTP via Email (integrate Email service)
        }

        return response()->json(['message' => __('auth.otp_sent', ['query' => $contact])]);
    }

    /**
     * @throws CustomApiException
     */
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'query' => 'required',
            'otp' => 'required|digits:5',
        ]);

        // Validate query
        if (filter_var($data['query'], FILTER_VALIDATE_EMAIL)) {
            $contact = $data['query'];
            $contactType = 'email';
        } elseif (preg_match('/^(\+98|98|0)?9\d{9}$/', $data['query'])) {
            $contact = preg_replace('/^0/', '98', $data['query']);
            $contact = ltrim($contact, '+');
            $contactType = 'phone';
        } else {
            throw new CustomApiException(__('auth.invalid_phone_email'), 400);

        }

        // Retrieve OTP from cache
        $cachedOtp = Cache::get('otp_' . $contact);
        Log::info("cached otp for $contact is $cachedOtp you send : " . $data['otp']);

        if (App::environment('production')) {
            // Production environment: validate against cached OTP
            if ($cachedOtp != $data['otp']) {
                throw new CustomApiException(__('auth.invalid_otp'), 401);

            }
        } else {
            // Non-production environment: validate against default OTP
            if ($request->otp !== '12345') {
                throw new CustomApiException(__('auth.invalid_otp'), 401);

            }
        }

        // convert phone number to 98 format for save in db
        if ($contactType == 'phone') {
            $contact = normalizePhoneNumber($contact);
            if ($contact) {
                $contact = "98" . $contact;
            } else {
                throw new CustomApiException(__('auth.invalid_phone_number'), 400);
            }
        }

        // OTP is valid, register or login user
        $user = User::query()->firstOrCreate(
            [$contactType => $contact],
            ['email' => ($contactType === 'email') ? $contact : null, 'phone' => ($contactType === 'phone') ? $contact : null]
        );

        // Clear OTP from cache
        Cache::forget('otp_' . $contact);

        // Create token for the user
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        $token->save();

        // Create user session
//        UserSession::query()->create([
//            'user_id' => $user->id,
//            'device_name' => $request->header('User-Agent'),
//            'ip_address' => $request->ip(),
//            'token_id' => $token->id,
//            'last_used_at' => Carbon::now(),
//        ]);

        return response()->json([
            'message' => 'User authenticated successfully',
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
        ]);
    }

    # Telegram mini app
    # this method responsible to register and login users from telegram mini app
    public function tmaAuthentication(Request $request){

        $authorizationHeader = $request->header('Authorization');
        // Check if the Authorization header starts with 'tma'
        if (!str_starts_with($authorizationHeader, 'tma')) {
            return response()->json(['error' => 'Invalid authorization header'], 401);
        }
        // Extract the init data from the Authorization header
        $initData = substr($authorizationHeader, 4);

        if (ValidateTelegramInitData::isSafe(self::MINI_APP_TOKEN, $initData)) {

            parse_str($initData, $pairs);
            $user_data = json_decode($pairs['user'],true);
            $telegram_user_id = $user_data['id'];
            $telegram_first_name = $user_data['first_name'];
            $telegram_last_name = $user_data['last_name'];
            $telegram_username = $user_data['username'];

            $user = User::where('telegram_id', $telegram_user_id)->first();
            if (!$user){
                $user = User::query()->create([
                    'telegram_id' => $telegram_user_id,
                    'last_name' => $telegram_last_name,
                    'first_name' => $telegram_first_name,
                    'username' => $telegram_username,
                    'verified_at' => now(),
                ]);

            }
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->save();
            return response()->json([
                'message' => 'User registered successfully',
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'user' => $user,
                'expires_at' => $tokenResult->token->expires_at->toDateTimeString(),
            ]);
        } else {
            return response()->json(['error' => 'Invalid init data'], 400);
        }
    }
}
