<?php

namespace Tests\Unit\Observers;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InvoiceObserver Unit Tests
 * 
 * Tests Observer-specific behavior:
 * - BR-INV-004: Deletion prevention
 * - BR-INV-003: Reactivation prevention
 * - Audit logging
 * 
 * Note: Customer balance updates are handled by InvoiceController, not Observer.
 * See InvoiceController::store() lines 111-114.
 */
class InvoiceObserverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * BR-INV-004: Deleting invoice throws exception
     */
    public function it_prevents_invoice_deletion(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(\App\Exceptions\BusinessException::class);
        $this->expectExceptionMessageMatches('/لا يمكن حذف الفواتير/');

        $invoice->delete();
    }

    /**
     * @test
     * BR-INV-003: Cannot reactivate cancelled invoice
     */
    public function it_prevents_reactivating_cancelled_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => 'cancelled',
            'balance' => 0,
            'paid_amount' => 0,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن إعادة تفعيل فاتورة ملغاة');

        $invoice->update(['status' => 'active']);
    }

    /**
     * @test
     * Audit log is created on invoice creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        $user = $this->actingAsUser();

        $invoice = Invoice::factory()->create([
            'created_by' => $user->id,
            'total' => 500,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => 'App\\Models\\Invoice',
            'model_id' => $invoice->id,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
    }
}
