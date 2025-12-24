<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Services\Contracts\ServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Base Service Class
 * 
 * Provides common functionality for all services including:
 * - Database transactions with automatic rollback
 * - Consistent logging
 * - Error handling
 * 
 * All business logic services should extend this class.
 * 
 * @package App\Services
 */
abstract class BaseService implements ServiceInterface
{
    /**
     * Execute a callback within a database transaction.
     * 
     * Automatically handles commit on success and rollback on failure.
     * Logs transaction start and completion for debugging.
     *
     * @param callable $callback The callback to execute
     * @return mixed The result of the callback
     * @throws \Throwable If the callback throws an exception
     */
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Execute a callback within a database transaction with logging.
     * 
     * Same as transaction() but adds automatic logging of the operation.
     *
     * @param string $operation Name of the operation for logging
     * @param callable $callback The callback to execute
     * @param array $context Additional context for logging
     * @return mixed The result of the callback
     * @throws \Throwable If the callback throws an exception
     */
    protected function transactionWithLog(string $operation, callable $callback, array $context = []): mixed
    {
        $this->log("{$operation} started", $context);

        try {
            $result = DB::transaction($callback);
            $this->log("{$operation} completed", $context);
            return $result;
        } catch (\Throwable $e) {
            $this->logError("{$operation} failed", [
                ...$context,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Log an informational message with service context.
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    protected function log(string $message, array $context = []): void
    {
        Log::info("[{$this->getServiceName()}] {$message}", [
            'service' => $this->getServiceName(),
            'user_id' => auth()->id(),
            ...$context,
        ]);
    }

    /**
     * Log a warning message with service context.
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning("[{$this->getServiceName()}] {$message}", [
            'service' => $this->getServiceName(),
            'user_id' => auth()->id(),
            ...$context,
        ]);
    }

    /**
     * Log an error message with service context.
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error("[{$this->getServiceName()}] {$message}", [
            'service' => $this->getServiceName(),
            'user_id' => auth()->id(),
            ...$context,
        ]);
    }

    /**
     * Throw a business exception with consistent formatting.
     *
     * @param string $code Error code (e.g., 'USER_001')
     * @param string $messageAr Arabic error message
     * @param string $messageEn English error message
     * @throws BusinessException
     */
    protected function throwBusinessError(string $code, string $messageAr, string $messageEn): never
    {
        throw new BusinessException($code, $messageAr, $messageEn);
    }

    /**
     * Get the service name for logging purposes.
     * 
     * Each service should implement this to return its class name
     * or a human-readable identifier.
     *
     * @return string The service name
     */
    abstract protected function getServiceName(): string;
}
