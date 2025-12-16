<?php

namespace App\Exceptions;

/**
 * Business Exception - Base class for all business rule exceptions
 * تحسين 2025-12-13: توحيد أكواد الأخطاء
 */
class BusinessException extends \Exception
{
    protected string $errorCode;

    protected string $messageAr;

    protected string $messageEn;

    public function __construct(
        string $errorCode,
        string $messageAr,
        string $messageEn = '',
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->messageAr = $messageAr;
        $this->messageEn = $messageEn ?: $messageAr;

        parent::__construct($messageAr, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getMessageAr(): string
    {
        return $this->messageAr;
    }

    public function getMessageEn(): string
    {
        return $this->messageEn;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->messageAr,
            'message_en' => $this->messageEn,
        ];
    }
}
