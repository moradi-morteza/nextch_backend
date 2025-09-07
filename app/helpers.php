<?php

use App\Exceptions\CustomApiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


// Helper to detect unique violations in a portable-ish way
function isUniqueConstraintViolation(QueryException $e): bool
{
    // MySQL/MariaDB: 1062, Postgres: 23505, SQLite: 19, SQLSTATE 23000 class
    $sqlState = $e->errorInfo[0] ?? null;
    $driverCode = $e->errorInfo[1] ?? null;

    return $sqlState === '23000'
        || $driverCode === 1062
        || $sqlState === '23505'
        || $driverCode === 19;
}

function getUserAvatarUrl(string $userId, string $file_name): string
{
    return Storage::disk('s3')->url('users/' . $userId . '/' . $file_name);
}

function getPersonAvatarUrl(string $user_id,string $person_id, string $file_name): string
{
    return Storage::disk('s3')->url('users/' . $user_id . '/persons/'.$person_id.'/' . $file_name);
}

function getEmptyAvatarUrl(): string
{
    try {
        return Storage::disk('s3')->url('image/avatar.jpg');
    } catch (\Throwable $e) {
        // Log the error for debugging (optional)
        \Log::warning("Fallback to local avatar: {$e->getMessage()}");
        // Fallback to a default local avatar
        return asset('images/avatar.jpg');
    }
}

function normalizePhoneNumber($phoneNumber): array|string|null
{
    // Remove any non-numeric characters just in case
    $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

    // Check if the number starts with '98' (Iran country code) or '0', and remove it
    if (str_starts_with($phoneNumber, '98')) {
        // Remove '98' prefix
        $phoneNumber = substr($phoneNumber, 2);
    } elseif (str_starts_with($phoneNumber, '0')) {
        // Remove leading '0'
        $phoneNumber = substr($phoneNumber, 1);
    }

    if (strlen($phoneNumber) != 10) {
        return null;
    }

    return $phoneNumber; // Should now be in the format 9025075234
}

/**
 * @throws CustomApiException
 */
function uploadImage(string $imageBase64, string $uploadPath, string $prefix_file_name = ''): string
{
    if (preg_match('/^data:image\/(\w+);base64,/', $imageBase64, $type)) {
        $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
        $type = strtolower($type[1]); // jpg, jpeg, png

        // Check if the image extension is valid
        if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
            throw new CustomApiException(__('errors.invalid_image_type'), 422);
        }

        $imageData = base64_decode($imageBase64);

        $sizeLimit = 500 * 1024; // 500KB in bytes
        if (strlen($imageData) > $sizeLimit) {
            throw new CustomApiException(__('errors.invalid_image_size_500'), 500);
        }

        $fileName = $prefix_file_name . uniqid() .  '.' . $type;
        $uploadPath = $uploadPath . '/' . $fileName;
        Log::info($uploadPath);
        $uploaded = Storage::disk('s3')->put($uploadPath, $imageData);
        Log::error($uploaded);
        if ($uploaded) {
            return $fileName;
        } else {
            throw new CustomApiException(__('errors.upload_image_failed'), 500);
        }
    } else {
        throw new CustomApiException(__('errors.invalid_image_data'), 500);
    }
}

function deleteImage(string $filePath): bool
{
    try {
        // Check if the file exists before attempting to delete
        if (Storage::disk('s3')->exists($filePath)) {
            $deleted = Storage::disk('s3')->delete($filePath);

            if ($deleted) {
                Log::info("Image deleted successfully: {$filePath}");
                return true;
            } else {
                Log::error("Failed to delete image: {$filePath}");
                return false;
            }
        } else {
            Log::warning("Image not found for deletion: {$filePath}");
            return false;
        }
    } catch (\Exception $e) {
        Log::error("Error deleting image: {$filePath}. Error: {$e->getMessage()}");
        return false;
    }
}

function sendFcm(array $tokens, $data_array)
{
    Log::info("-------------------------- Start Send Notification ------------------------");
    Log::info($data_array);
    $server_key = "";
    $url = 'https://fcm.googleapis.com/fcm/send';
    $fields = array(
        'registration_ids' => $tokens,
        'data' => $data_array,
        'time_to_live' => 259200, // 3 days in seconds
    );
    $headers = array(
        'Authorization:key=' . $server_key,
        'Content-Type: application/json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    Log::info($result);
    Log::info($info);
    Log::info("-------------------------- End Send Notification ------------------------");
    $code = $info['http_code'];
    curl_close($ch);
    return $result;
}
