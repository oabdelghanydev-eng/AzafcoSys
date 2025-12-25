<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DatabaseResetService
{
    /**
     * Check if the feature is allowed in current environment
     */
    public function isAllowedEnvironment(): bool
    {
        // Only allow in local/testing by default
        // Override with ALLOW_DATABASE_RESET=true in .env for staging
        $allowedEnvs = ['local', 'testing'];
        $currentEnv = config('app.env');

        // Allow override via explicit config
        if (config('app.allow_database_reset') === true) {
            return true;
        }

        return in_array($currentEnv, $allowedEnvs, true);
    }

    /**
     * Check if reset password is configured
     */
    public function isConfigured(): bool
    {
        $password = config('app.admin_reset_password');
        return !empty($password) && strlen($password) >= 8;
    }

    /**
     * Validate the reset password using constant-time comparison
     * Prevents timing attacks
     */
    public function validatePassword(string $password): bool
    {
        $resetPassword = config('app.admin_reset_password');

        if (empty($resetPassword)) {
            return false;
        }

        // Use hash_equals for constant-time comparison (prevents timing attacks)
        return hash_equals($resetPassword, $password);
    }

    /**
     * Execute the database reset
     * 
     * @throws RuntimeException if environment not allowed
     * @throws \Exception on migration failure
     */
    public function execute(int $userId, string $userName, string $ip): string
    {
        // Double-check environment before destructive operation
        if (!$this->isAllowedEnvironment()) {
            throw new RuntimeException(
                'Database reset is not allowed in ' . config('app.env') . ' environment. ' .
                'Set ALLOW_DATABASE_RESET=true in .env to override.'
            );
        }

        $this->logInitiation($userId, $userName, $ip);

        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $output = Artisan::output();

        $this->logCompletion($userId, $output);

        return $output;
    }

    /**
     * Log the initiation of database reset
     */
    private function logInitiation(int $userId, string $userName, string $ip): void
    {
        Log::channel('single')->warning('🚨 DATABASE RESET initiated', [
            'user_id' => $userId,
            'user_name' => $userName,
            'ip' => $ip,
            'environment' => config('app.env'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log successful completion
     */
    private function logCompletion(int $userId, string $output): void
    {
        Log::channel('single')->warning('✅ DATABASE RESET completed', [
            'user_id' => $userId,
            'output_preview' => substr($output, 0, 300),
        ]);
    }

    /**
     * Log failure
     */
    public function logFailure(int $userId, string $error): void
    {
        Log::channel('single')->error('❌ DATABASE RESET failed', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }
}
