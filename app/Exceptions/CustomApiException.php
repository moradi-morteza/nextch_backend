<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CustomApiException extends Exception
{
    protected $message;
    public mixed $statusCode;

    public function __construct($message = 'Custom Error', $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }

    # after define custom exception add it on handler.php
}
