<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set Arabic as default locale for all tests
        app()->setLocale('ar');

        // Note: Seed essential data only if seeder exists
        // $this->seed(\Database\Seeders\TestDataSeeder::class);
    }

    /**
     * Create and authenticate a user with specific permissions.
     *
     * @param array $permissions
     * @param bool $isAdmin
     * @return \App\Models\User
     */
    protected function actingAsUser(array $permissions = [], bool $isAdmin = false): \App\Models\User
    {
        $user = \App\Models\User::factory()->create([
            'permissions' => $permissions,
            'is_admin' => $isAdmin,
        ]);

        $this->actingAs($user, 'sanctum');

        return $user;
    }

    /**
     * Create and authenticate an admin user.
     *
     * @return \App\Models\User
     */
    protected function actingAsAdmin(): \App\Models\User
    {
        return $this->actingAsUser([], true);
    }

    /**
     * Assert that the response contains a business exception error code.
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param string $errorCode
     * @return void
     */
    protected function assertBusinessError($response, string $errorCode): void
    {
        // Just check that error code exists somewhere in response
        $response->assertJsonFragment([
            'code' => $errorCode,
        ]);
    }

    /**
     * Set a specific date/time for testing.
     *
     * @param string $date
     * @return void
     */
    protected function setTestDate(string $date = 'now'): void
    {
        $this->travelTo(now()->parse($date));
    }

    /**
     * Open a working day for testing.
     * Creates a DailyReport in database (system uses database-based working day).
     *
     * @param string|null $date
     * @return void
     */
    protected function openWorkingDay(?string $date = null): void
    {
        $date = $date ?? today()->toDateString();

        // Create DailyReport in database (system uses database-based working day)
        \App\Models\DailyReport::firstOrCreate(
            ['date' => $date],
            ['status' => 'open']
        );
    }
}

