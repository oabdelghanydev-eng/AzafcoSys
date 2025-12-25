<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $chatId;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id', '');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Check if Telegram is configured
     */
    public function isConfigured(): bool
    {
        // Disable in testing environment
        if (app()->environment('testing')) {
            return false;
        }

        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Send a text message
     */
    public function sendMessage(string $message, ?string $chatId = null): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Telegram not configured');
            return false;
        }

        try {
            $response = Http::post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId ?? $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if (!$response->successful()) {
                Log::error('Telegram sendMessage failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a document/file
     */
    public function sendDocument(string $filePath, ?string $caption = null, ?string $chatId = null): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Telegram not configured');
            return false;
        }

        if (!file_exists($filePath)) {
            Log::error('File not found for Telegram', ['path' => $filePath]);
            return false;
        }

        try {
            $response = Http::attach(
                'document',
                file_get_contents($filePath),
                basename($filePath)
            )->post("{$this->apiUrl}/sendDocument", [
                        'chat_id' => $chatId ?? $this->chatId,
                        'caption' => $caption ?? '',
                        'parse_mode' => 'HTML',
                    ]);

            if (!$response->successful()) {
                Log::error('Telegram sendDocument failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram sendDocument exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send daily report
     */
    public function sendDailyReport(string $pdfPath, string $date, array $summary = []): bool
    {
        $currency = Setting::getValue('currency_symbol', 'ر.ق');

        $caption = "📊 <b>تقرير التقفيل اليومي</b>\n";
        $caption .= "📅 التاريخ: {$date}\n\n";

        if (!empty($summary)) {
            $caption .= "💰 إجمالي المبيعات: " . number_format($summary['total_sales'] ?? 0, 2) . " {$currency}\n";
            $caption .= "💵 إجمالي التحصيلات: " . number_format($summary['total_collections'] ?? 0, 2) . " {$currency}\n";
            $caption .= "📤 إجمالي المصروفات: " . number_format($summary['total_expenses'] ?? 0, 2) . " {$currency}\n";
        }

        return $this->sendDocument($pdfPath, $caption);
    }

    /**
     * Send settlement report
     */
    public function sendSettlementReport(string $pdfPath, string $shipmentNumber, string $supplierName, array $summary = []): bool
    {
        $currency = Setting::getValue('currency_symbol', 'ر.ق');

        $caption = "📦 <b>تقرير تسوية الشحنة</b>\n";
        $caption .= "🔢 رقم الشحنة: {$shipmentNumber}\n";
        $caption .= "👤 المورد: {$supplierName}\n\n";

        if (!empty($summary)) {
            $caption .= "💰 إجمالي المبيعات: " . number_format($summary['total_sales'] ?? 0, 2) . " {$currency}\n";
            $caption .= "📊 العمولة (6%): " . number_format($summary['commission'] ?? 0, 2) . " {$currency}\n";
            $caption .= "✅ صافي المورد: " . number_format($summary['final_balance'] ?? 0, 2) . " {$currency}\n";
        }

        return $this->sendDocument($pdfPath, $caption);
    }

    /**
     * Send shipment closed notification
     */
    public function sendShipmentClosedNotification(
        string $shipmentNumber,
        string $supplierName,
        int $totalItems,
        int $totalCartons,
        float $totalWeight,
        string $closedBy
    ): bool {
        $message = "🚚 <b>تم إغلاق الشحنة</b>\n\n";
        $message .= "🔢 رقم الشحنة: <b>{$shipmentNumber}</b>\n";
        $message .= "👤 المورد: {$supplierName}\n";
        $message .= "📦 عدد الأصناف: {$totalItems}\n";
        $message .= "📦 عدد الكراتين: " . number_format($totalCartons) . "\n";
        $message .= "⚖️ إجمالي الوزن: " . number_format($totalWeight, 2) . " kg\n\n";
        $message .= "👷 أغلقها: {$closedBy}\n";
        $message .= "🕐 الوقت: " . now()->format('Y-m-d H:i');

        return $this->sendMessage($message);
    }
}
