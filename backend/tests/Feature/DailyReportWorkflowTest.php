<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DailyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daily Report Workflow Feature Tests
 * 
 * Tests all BR-DAY rules:
 * - BR-DAY-001: Opening working day session
 * - BR-DAY-002: Operations use session date
 * - BR-DAY-003: Available dates for opening  
 * - BR-DAY-004: Prevent work without open day
 * - BR-DAY-005: Close daily report
 * - BR-DAY-006: Reopen closed day
 */
class DailyReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * BR-DAY-001: User can open a working day
     */
    public function it_opens_working_day_and_stores_in_session(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close']);
        $date = today()->toDateString();

        // Act
        $response = $this->postJson('/api/daily/open', [
            'date' => $date,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'success' => true,
            'date' => $date,
        ]);

        // Verify session
        $this->assertEquals($date, session('working_date'));
    }

    /**
     * @test
     * BR-DAY-004: Cannot create invoice without open working day
     */
    public function it_prevents_invoice_creation_without_open_working_day(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.create']);

        // Act - Try to create invoice without opening day
        $response = $this->postJson('/api/invoices', [
            'customer_id' => 1,
            'items' => [],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonPath('error.message', 'يجب فتح يومية عمل أولاً');
    }

    /**
     * @test
     * BR-DAY-002: Operations take working_date from session
     */
    public function it_assigns_session_date_to_created_invoice(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close', 'invoices.create']);
        $workingDate = today()->subDay()->toDateString();

        // Open yesterday's working day
        $this->postJson('/api/daily/open', ['date' => $workingDate]);

        $customer = \App\Models\Customer::factory()->create();
        $shipmentItem = \App\Models\ShipmentItem::factory()->create([
            'remaining_quantity' => 100,
        ]);

        // Act - Create invoice (without specifying date)
        $response = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $shipmentItem->product_id,
                    'shipment_item_id' => $shipmentItem->id,
                    'quantity' => 10,
                    'price_per_kg' => 50,
                ],
            ],
        ]);

        // Assert
        $response->assertStatus(201);

        // Invoice should have the working_date
        $invoice = \App\Models\Invoice::first();
        $this->assertEquals($workingDate, $invoice->date->toDateString());
    }

    /**
     * @test
     * BR-DAY-003: Available dates exclude closed ones
     */
    public function it_returns_available_dates_excluding_closed(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close']);

        // Close yesterday
        DailyReport::factory()->create([
            'date' => today()->subDay(),
            'status' => 'closed',
        ]);

        // Act
        $response = $this->getJson('/api/daily/available');

        // Assert
        $response->assertStatus(200);
        $availableDates = $response->json('data.dates');

        // Should not include yesterday (closed)
        $this->assertNotContains(today()->subDay()->toDateString(), $availableDates);

        // Should include today
        $this->assertContains(today()->toDateString(), $availableDates);
    }

    /**
     * @test
     * BR-DAY-003: Cannot open date outside backdated window
     */
    public function it_prevents_opening_date_outside_backdated_window(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close']);

        // Assuming backdated_days = 2 (default)
        $tooOldDate = today()->subDays(3)->toDateString();

        // Act
        $response = $this->postJson('/api/daily/open', [
            'date' => $tooOldDate,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'التاريخ خارج النطاق المسموح',
        ]);
    }

    /**
     * @test
     * BR-DAY-005: User can close working day
     */
    public function it_closes_working_day_and_generates_report(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close']);
        $date = today()->toDateString();

        // Open the day first
        $this->postJson('/api/daily/open', ['date' => $date]);

        // Act - Close the day
        $response = $this->postJson('/api/daily/close', [
            'notes' => 'End of day close',
        ]);

        // Assert
        $response->assertStatus(200);

        // Daily report should be created
        $this->assertDatabaseHas('daily_reports', [
            'date' => $date,
            'status' => 'closed',
        ]);

        // Session should be cleared
        $this->assertNull(session('working_date'));
    }

    /**
     * @test
     * BR-DAY-005: Closing day calculates totals
     */
    public function it_calculates_totals_when_closing_day(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close', 'invoices.create', 'collections.create']);
        $date = today()->toDateString();

        $this->postJson('/api/daily/open', ['date' => $date]);

        // Create some transactions
        $customer = \App\Models\Customer::factory()->create();
        $shipmentItem = \App\Models\ShipmentItem::factory()->create([
            'remaining_quantity' => 100,
        ]);

        // Invoice for 500
        $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $shipmentItem->product_id,
                    'shipment_item_id' => $shipmentItem->id,
                    'quantity' => 10,
                    'price_per_kg' => 50,
                ],
            ],
        ]);

        // Collection for 300
        $invoice = \App\Models\Invoice::first();
        $this->postJson('/api/collections', [
            'customer_id' => $customer->id,
            'amount' => 300,
            'payment_method' => 'cash',
        ]);

        // Act - Close
        $this->postJson('/api/daily/close');

        // Assert
        $report = DailyReport::where('date', $date)->first();

        $this->assertEquals(500, $report->total_sales);
        $this->assertEquals(300, $report->total_collections);
        $this->assertEquals(1, $report->invoices_count);
        $this->assertEquals(1, $report->collections_count);
    }

    /**
     * @test
     * BR-DAY-003: Cannot open already closed date
     */
    public function it_prevents_opening_already_closed_date(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close']);
        $date = today()->subDay()->toDateString();

        DailyReport::factory()->create([
            'date' => $date,
            'status' => 'closed',
        ]);

        // Act
        $response = $this->postJson('/api/daily/open', [
            'date' => $date,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'هذا التاريخ مغلق بالفعل',
        ]);
    }

    /**
     * @test
     * BR-DAY-006: Admin can reopen closed day
     */
    public function it_allows_reopening_closed_day_with_permission(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.reopen']);
        $date = today()->subDay()->toDateString();

        $report = DailyReport::factory()->create([
            'date' => $date,
            'status' => 'closed',
        ]);

        // Act
        $response = $this->postJson("/api/daily/{$date}/reopen");

        // Assert
        $response->assertStatus(200);

        // Status should change to open
        $this->assertEquals('open', $report->fresh()->status);
    }

    /**
     * @test
     * BR-DAY-006: Reopening without permission fails
     */
    public function it_prevents_reopening_without_permission(): void
    {
        // Arrange - User without daily.reopen permission
        $user = $this->actingAsUser(['daily.close']);
        $date = today()->subDay()->toDateString();

        DailyReport::factory()->create([
            'date' => $date,
            'status' => 'closed',
        ]);

        // Act
        $response = $this->postJson("/api/daily/{$date}/reopen");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * @test
     * Full workflow: Open → Operate → Close → Reopen → Operate → Close
     */
    public function it_supports_full_daily_workflow(): void
    {
        // Arrange
        $user = $this->actingAsUser(['daily.close', 'daily.reopen', 'invoices.create']);
        $date = today()->toDateString();

        // Step 1: Open day
        $response = $this->postJson('/api/daily/open', ['date' => $date]);
        $response->assertStatus(200);

        // Step 2: Create invoice (should succeed)
        $customer = \App\Models\Customer::factory()->create();
        $shipmentItem = \App\Models\ShipmentItem::factory()->create([
            'remaining_quantity' => 100,
        ]);

        $response = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $shipmentItem->product_id,
                    'shipment_item_id' => $shipmentItem->id,
                    'quantity' => 10,
                    'price_per_kg' => 50,
                ],
            ],
        ]);
        $response->assertStatus(201);

        // Step 3: Close day
        $this->postJson('/api/daily/close');
        $this->assertDatabaseHas('daily_reports', [
            'date' => $date,
            'status' => 'closed',
        ]);

        // Step 4: Try to create invoice (should fail - day closed)
        $response = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'items' => [],
        ]);
        $response->assertStatus(422);

        // Step 5: Reopen day
        $this->postJson("/api/daily/{$date}/reopen");
        $this->postJson('/api/daily/open', ['date' => $date]);

        // Step 6: Create invoice (should succeed again)
        $response = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $shipmentItem->product_id,
                    'shipment_item_id' => $shipmentItem->id,
                    'quantity' => 5,
                    'price_per_kg' => 50,
                ],
            ],
        ]);
        $response->assertStatus(201);
    }
}
