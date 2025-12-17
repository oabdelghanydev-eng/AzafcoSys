<?php

namespace App\Exceptions;

/**
 * Business Exception - Base class for all business rule exceptions
 * Enhanced 2025-12-17: Laravel i18n integration with Accept-Language support
 */
class BusinessException extends \Exception
{
    protected string $errorCode;

    protected string $messageAr;

    protected string $messageEn;

    /**
     * Create a new business exception
     *
     * @param string $errorCode Error code (e.g., 'INV_001')
     * @param string|null $messageAr Optional Arabic message (auto-loaded from translations if null)
     * @param string|null $messageEn Optional English message (auto-loaded from translations if null)
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $errorCode,
        ?string $messageAr = null,
        ?string $messageEn = null,
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;

        // Auto-load messages from translation files if not provided
        $this->messageAr = $messageAr ?? $this->translateError($errorCode, 'ar');
        $this->messageEn = $messageEn ?? $this->translateError($errorCode, 'en');

        // Use current locale for exception message
        $currentLocale = app()->getLocale();
        $message = $currentLocale === 'en' ? $this->messageEn : $this->messageAr;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get Arabic message
     */
    public function getMessageAr(): string
    {
        return $this->messageAr;
    }

    /**
     * Get English message
     */
    public function getMessageEn(): string
    {
        return $this->messageEn;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->messageAr,
            'message_en' => $this->messageEn,
        ];
    }

    /**
     * Translate error code to message
     */
    private function translateError(string $code, string $locale): string
    {
        $key = "errors.{$code}";
        $translation = trans($key, [], $locale);

        // If translation not found, return generic message
        if ($translation === $key) {
            return $locale === 'ar'
                ? "خطأ في العملية: {$code}"
                : "Operation error: {$code}";
        }

        return $translation;
    }
}

