<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Error Codes and Business Exception
 * تحسين 2025-12-13: اختبارات أكواد الخطأ
 */
class ErrorCodesTest extends TestCase
{
    /**
     * Test that all error codes have Arabic messages
     */
    public function test_all_error_codes_have_arabic_messages(): void
    {
        $codes = [
            ErrorCodes::INV_001,
            ErrorCodes::INV_002,
            ErrorCodes::INV_003,
            ErrorCodes::INVOICE_001,
            ErrorCodes::INVOICE_002,
            ErrorCodes::INVOICE_003,
            ErrorCodes::COL_001,
            ErrorCodes::COL_002,
            ErrorCodes::SHP_001,
            ErrorCodes::SHP_002,
            ErrorCodes::SHP_004,
        ];

        foreach ($codes as $code) {
            $message = ErrorCodes::getMessage($code);
            $this->assertNotEmpty($message, "Error code {$code} should have a message");
            $this->assertNotEquals('حدث خطأ غير متوقع', $message, "Error code {$code} should have a specific message");
        }
    }

    /**
     * Test that all error codes have English messages
     */
    public function test_all_error_codes_have_english_messages(): void
    {
        $codes = [
            ErrorCodes::INV_001,
            ErrorCodes::INVOICE_001,
            ErrorCodes::COL_001,
            ErrorCodes::SHP_001,
        ];

        foreach ($codes as $code) {
            $messageEn = ErrorCodes::getMessageEn($code);
            $this->assertNotEmpty($messageEn, "Error code {$code} should have an English message");
            $this->assertNotEquals('An unexpected error occurred', $messageEn, "Error code {$code} should have a specific English message");
        }
    }

    /**
     * Test BusinessException creation and properties
     */
    public function test_business_exception_stores_all_properties(): void
    {
        $exception = new BusinessException(
            ErrorCodes::COL_001,
            'لا يمكن حذف التحصيلات',
            'Cannot delete collections'
        );

        $this->assertEquals(ErrorCodes::COL_001, $exception->getErrorCode());
        $this->assertEquals('لا يمكن حذف التحصيلات', $exception->getMessageAr());
        $this->assertEquals('Cannot delete collections', $exception->getMessageEn());
        $this->assertEquals('لا يمكن حذف التحصيلات', $exception->getMessage());
    }

    /**
     * Test BusinessException toArray method
     */
    public function test_business_exception_to_array(): void
    {
        $exception = new BusinessException(
            ErrorCodes::INVOICE_001,
            ErrorCodes::getMessage(ErrorCodes::INVOICE_001),
            ErrorCodes::getMessageEn(ErrorCodes::INVOICE_001)
        );

        $array = $exception->toArray();

        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('message_en', $array);
        $this->assertEquals(ErrorCodes::INVOICE_001, $array['code']);
    }

    /**
     * Test specific error code messages
     */
    public function test_specific_error_code_messages(): void
    {
        // Invoice
        $this->assertStringContainsString('حذف', ErrorCodes::getMessage(ErrorCodes::INVOICE_001));

        // Collection
        $this->assertStringContainsString('حذف', ErrorCodes::getMessage(ErrorCodes::COL_001));

        // Shipment
        $this->assertStringContainsString('مُصفاة', ErrorCodes::getMessage(ErrorCodes::SHP_001));

        // Inventory
        $this->assertStringContainsString('المخزون', ErrorCodes::getMessage(ErrorCodes::INV_001));
    }
}
