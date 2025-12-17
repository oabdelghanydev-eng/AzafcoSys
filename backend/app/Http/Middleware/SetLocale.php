<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetLocale Middleware
 *
 * Sets application locale based on Accept-Language header.
 * Supports Arabic (ar) and English (en).
 *
 * Usage:
 * - Header: Accept-Language: en
 * - Header: Accept-Language: ar
 * - Default: ar (Arabic)
 */
class SetLocale
{
    /**
     * Supported locales
     */
    private const SUPPORTED_LOCALES = ['ar', 'en'];

    /**
     * Default locale
     */
    private const DEFAULT_LOCALE = 'ar';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        App::setLocale($locale);

        // Also add to response headers for clarity
        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $response->headers->set('Content-Language', $locale);
        }

        return $response;
    }

    /**
     * Determine locale from request
     */
    private function determineLocale(Request $request): string
    {
        // 1. Check Accept-Language header
        $header = $request->header('Accept-Language');

        if ($header) {
            $locale = $this->parseAcceptLanguage($header);

            if (in_array($locale, self::SUPPORTED_LOCALES)) {
                return $locale;
            }
        }

        // 2. Check query parameter (for testing)
        $query = $request->query('lang');

        if ($query && in_array($query, self::SUPPORTED_LOCALES)) {
            return $query;
        }

        // 3. Default to Arabic
        return self::DEFAULT_LOCALE;
    }

    /**
     * Parse Accept-Language header
     */
    private function parseAcceptLanguage(string $header): ?string
    {
        // Accept-Language: en-US,en;q=0.9,ar;q=0.8
        // We take the first two characters (language code)
        $parts = explode(',', $header);
        $first = $parts[0] ?? '';

        // Extract language code (before hyphen or semicolon)
        $lang = strtok($first, '-;');

        return strtolower(trim($lang));
    }
}
