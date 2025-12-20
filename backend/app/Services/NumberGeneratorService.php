<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    /**
     * Generate next sequential number for a model
     *
     * @param  string  $type  Type: invoice, collection, shipment, return, expense
     * @return string Generated number like "INV-00001"
     */
    public function generate(string $type): string
    {
        $prefixKey = "{$type}_number_prefix";
        $digitsKey = "{$type}_number_digits";

        $prefix = Setting::getValue($prefixKey, strtoupper(substr($type, 0, 3)) . '-');
        $digits = Setting::getValue($digitsKey, 5);

        // Get the table name based on type
        $table = $this->getTableName($type);
        $column = $this->getColumnName($type);

        // Get the last number
        $lastRecord = DB::table($table)
            ->orderByDesc('id')
            ->first([$column]);

        if ($lastRecord) {
            // Extract number from last record
            $lastNumber = $lastRecord->{$column};
            $numericPart = (int) preg_replace('/[^0-9]/', '', $lastNumber);
            $nextNumber = $numericPart + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Get table name for type
     */
    private function getTableName(string $type): string
    {
        return match ($type) {
            'invoice' => 'invoices',
            'collection' => 'collections',
            'shipment' => 'shipments',
            'return' => 'returns',
            'expense' => 'expenses',
            'supplier' => 'suppliers',
            'customer' => 'customers',
            'credit_note', 'debit_note' => 'credit_notes',
            default => $type . 's',
        };
    }

    /**
     * Get column name for type
     */
    private function getColumnName(string $type): string
    {
        return match ($type) {
            'invoice' => 'invoice_number',
            'collection' => 'receipt_number',
            'shipment' => 'number',
            'return' => 'return_number',
            'expense' => 'expense_number',
            'supplier' => 'code',
            'customer' => 'code',
            'credit_note', 'debit_note' => 'note_number',
            default => 'number',
        };
    }
}
