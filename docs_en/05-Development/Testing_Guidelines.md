# Testing Guidelines - 2025 Best Practices

## 📋 Overview

This document outlines the testing standards and best practices for the Inventory Management System backend.

---

## 🎯 Testing Philosophy

1. **Behavior over Implementation**: Test what the code does, not how it does it
2. **AAA Pattern**: Arrange → Act → Assert
3. **Single Responsibility**: One test, one concept
4. **Clear Intent**: Test names describe the scenario being tested
5. **Fast \u0026 Isolated**: Tests run independently and quickly

---

## 📊 Coverage Goals

| Module | Target Coverage | Priority |
|--------|----------------|----------|
| **Services** | 90% | CRITICAL |
| **Observers** | 85% | HIGH |
| **Controllers** | 75% | MEDIUM |
| **Policies** | 80% | HIGH |
| **Models** | 60% | LOW |

**Overall Target**: **80% minimum**

---

## 🏗️ Test Structure

### File Organization

```
tests/
├── Feature/              # Integration tests (full HTTP requests)
│   ├── InvoiceApiTest.php
│   ├── DailyReportWorkflowTest.php
│   └── ...
├── Unit/                 # Unit tests (isolated)
│   ├── Services/
│   │   ├── CollectionServiceTest.php
│   │   ├── FifoAllocatorServiceTest.php
│   │   └── ...
│   └── Observers/
│       ├── InvoiceObserverTest.php
│       └── ...
└── TestCase.php         # Base test class
```

---

## ✍️ Naming Conventions

### Test Class Names
```php
// Pattern: {ClassBeingTested}Test
CollectionServiceTest
InvoiceObserverTest
DailyReportWorkflowTest
```

### Test Method Names
```php
// Pattern: it_{action}_{expected_result}()

/** @test */
public function it_allocates_collection_to_oldest_invoice_first(): void

/** @test */
public function it_prevents_invoice_deletion(): void

/** @test */
public function it_calculates_totals_when_closing_day(): void
```

---

## 🧪 Test Patterns

### AAA Pattern (Arrange-Act-Assert)

```php
public function it_increases_customer_balance_when_invoice_created(): void
{
    // Arrange - Set up test data
    $customer = Customer::factory()->create(['balance' => 100]);

    // Act - Perform the action
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'total' => 500,
    ]);

    // Assert - Verify the result
    $this->assertEquals(600, $customer->fresh()->balance);
}
```

### Database Testing

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase; // Reset database for each test

    public function it_creates_record(): void
    {
        $data = ['name' => 'Test'];
        
        $this->postJson('/api/resource', $data);
        
        $this->assertDatabaseHas('resources', $data);
    }
}
```

### Factory Usage

```php
// Good: Use factories for test data
$customer = Customer::factory()->create([
    'balance' => 1000,
    'is_active' => true,
]);

// Better: Use states for common scenarios
$customer = Customer::factory()->withDebt(1000)->create();

// Best: Use multiple records efficiently
$invoices = Invoice::factory()->count(3)->create([
    'customer_id' => $customer->id,
]);
```

---

## 🧰 Helper Methods

### Authentication Helpers

```php
// Create and authenticate user with permissions
$user = $this->actingAsUser(['invoices.create', 'invoices.edit']);

// Create and authenticate admin
$admin = $this->actingAsAdmin();
```

### Business Error Assertions

```php
$response = $this->postJson('/api/invoices/1', ['total' => 100]);

// Assert business error code
$this->assertBusinessError($response, 'INV_002');
```

### Date Testing

```php
// Set test date
$this->setTestDate('2025-12-15');

// Or use Laravel's built-in
$this->travelTo(now()->parse('2025-12-15'));
```

---

## 📝 Business Rules Documentation

Each test should reference the business rule it validates:

```php
/**
 * @test
 * BR-COL-002: FIFO Distribution (Oldest First)
 */
public function it_allocates_collection_to_oldest_invoice_first(): void
{
    // Test implementation
}
```

**Business Rule Prefixes**:
- `BR-INV-*`: Invoice rules
- `BR-COL-*`: Collection rules
- `BR-SHP-*`: Shipment rules
- `BR-FIFO-*`: FIFO inventory rules
- `BR-DAY-*`: Daily report rules

---

## 🔍 Testing Edge Cases

Always test:

1. **Happy Path**: Normal, expected behavior
2. **Boundary Conditions**: Min/max values, empty data
3. **Error Cases**: Invalid input, unauthorized access
4. **Race Conditions**: Concurrent operations (when applicable)
5. **State Transitions**: Status changes, workflow steps

### Example:

```php
// Happy path
public function it_creates_invoice_successfully(): void { }

// Boundary
public function it_handles_zero_amount_invoice(): void { }

// Error case
public function it_rejects_invoice_without_items(): void { }

// State transition
public function it_prevents_editing_cancelled_invoice(): void { }
```

---

## 🚫 What NOT to Do

### ❌ Don't Test Framework Code
```php
// Bad - Testing Eloquent
public function it_saves_to_database(): void
{
    $customer = new Customer();
    $customer->name = 'Test';
    $customer->save();
    
    $this->assertNotNull($customer->id);
}
```

### ❌ Don't Use Real Dates
```php
// Bad - Flaky test
$this->assertEquals(now(), $invoice->created_at);

// Good - Use time travel
$this->travelTo('2025-12-15 10:00:00');
$invoice = Invoice::factory()->create();
$this->assertEquals('2025-12-15 10:00:00', $invoice->created_at);
```

### ❌ Don't Share State Between Tests
```php
// Bad - Tests affect each other
protected static $customer;

public function test_a(): void
{
    self::$customer = Customer::create([...]);
}

public function test_b(): void
{
    self::$customer->update(...); // Depends on test_a
}

// Good - Each test is independent
public function test_a(): void
{
    $customer = Customer::factory()->create();
}

public function test_b(): void
{
    $customer = Customer::factory()->create();
}
```

---

## 🎯 Testing Services

```php
class CollectionServiceTest extends TestCase
{
    protected CollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CollectionService::class);
    }

    public function it_performs_fifo_allocation(): void
    {
        // Arrange
        $invoices = Invoice::factory()->count(3)->create();
        $collection = Collection::factory()->create();

        // Act
        $result = $this->service->allocatePayment($collection);

        // Assert
        $this->assertEquals(expected, $result);
    }
}
```

---

## 🎯 Testing Observers

```php
class InvoiceObserverTest extends TestCase
{
    public function it_updates_customer_balance_on_creation(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 100]);

        // Act - Observer fires automatically
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
        ]);

        // Assert - Observer side effect
        $this->assertEquals(600, $customer->fresh()->balance);
    }
}
```

---

## 🎯 Testing API Endpoints

```php
class InvoiceApiTest extends TestCase
{
    public function it_creates_invoice_via_api(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.create']);
        
        $data = [
            'customer_id' => Customer::factory()->create()->id,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 10,
                    'price_per_kg' => 50,
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/invoices', $data);

        // Assert
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'invoice_number',
                'total',
            ],
        ]);
    }
}
```

---

## 🏃 Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Suite
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### Run Specific File
```bash
php artisan test tests/Unit/Services/CollectionServiceTest.php
```

### Run with Coverage
```bash
php artisan test --coverage
php artisan test --coverage --min=80
```

### Generate HTML Coverage Report
```bash
XDEBUG_MODE=coverage php artisan test --coverage-html coverage-report
```

Then open `coverage-report/index.html` in browser.

---

## 📊 Continuous Integration

Tests should run on:
- ✅ Every commit (pre-commit hook)
- ✅ Every pull request (GitHub Actions)
- ✅ Before deployment

### Example CI Command
```yaml
# .github/workflows/tests.yml
- name: Run Tests
  run: php artisan test --coverage --min=80
```

---

## 🔧 Debugging Failed Tests

### Use `--filter`
```bash
php artisan test --filter=it_allocates_collection
```

### Use `dd()` in Tests
```php
public function it_does_something(): void
{
    $result = $this->service->calculate();
    dd($result); // Dump and die
    $this->assertEquals(100, $result);
}
```

### Check Database State
```php
dd(\App\Models\Invoice::all());
dd(DB::table('invoices')->get());
```

---

## 📚 Resources

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Pest PHP Documentation](https://pestphp.com/docs)
- Project Business Rules: `docs/01-Business_Logic/BR_Catalogue.md`

---

## ✅ Checklist Before Committing Tests

- [ ] Tests follow naming convention
- [ ] AAA pattern used
- [ ] Business rule referenced in docblock
- [ ] No hardcoded dates/times
- [ ] Database refreshed (`RefreshDatabase`)
- [ ] Factories used instead of manual creation
- [ ] Assertions are specific and descriptive
- [ ] Test is independent (doesn't rely on other tests)
- [ ] Coverage report shows green for tested code

---

*Last Updated: 2025-12-17*  
*Maintained by: Development Team*
