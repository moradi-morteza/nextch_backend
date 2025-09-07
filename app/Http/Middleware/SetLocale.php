<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        // Check if the client has sent a language preference in the request header
        $locale = $request->header('Accept-Language');

        // Or alternatively, check for a 'lang' parameter in the URL or request
        // $locale = $request->query('lang', 'en'); // Default to 'en' if no lang is provided

        // Set the locale for the application if the language is supported
        if (in_array($locale, ['en', 'fa', 'ar'])) { // Add the languages you support
            App::setLocale($locale);
        }

        return $next($request);
    }
}
