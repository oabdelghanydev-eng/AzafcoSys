<?php

namespace App\Helpers;

/**
 * Arabic PDF Helper
 * 
 * Helps render Arabic text correctly in DomPDF
 * Uses simple text processing without external dependencies
 */
class ArabicPdfHelper
{
    /**
     * Check if text contains Arabic characters
     */
    public static function isArabic(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
    }

    /**
     * Reverse Arabic text for proper DomPDF rendering
     * DomPDF renders RTL text in reverse, so we pre-reverse it
     */
    public static function fixArabicText(string $text): string
    {
        if (!self::isArabic($text)) {
            return $text;
        }

        // For DomPDF, we need to reverse the text and use RTL direction
        // This is a simple workaround for DomPDF's RTL issues
        $words = explode(' ', $text);
        $reversedWords = array_reverse($words);

        return implode(' ', $reversedWords);
    }

    /**
     * Wrap Arabic text in RTL span
     */
    public static function wrapRtl(string $text): string
    {
        if (!self::isArabic($text)) {
            return $text;
        }

        return '<span style="direction: rtl; unicode-bidi: bidi-override;">' . $text . '</span>';
    }

    /**
     * Process HTML content to fix all Arabic text
     */
    public static function processHtml(string $html): string
    {
        // Add RTL direction to body
        $html = str_replace('<body>', '<body style="direction: rtl;">', $html);

        return $html;
    }
}
