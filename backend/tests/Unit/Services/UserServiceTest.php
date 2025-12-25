<?php

namespace Tests\Unit\Services;

use App\DTOs\UserDTO;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UserService Unit Tests
 * 
 * Tests for user management business logic.
 */
class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    // ==========================================
    // CREATE USER TESTS
    // ==========================================

    /** @test */
    public function it_can_create_user_with_dto(): void
    {
        $dto = new UserDTO(
            name: 'Test User',
            email: 'test@example.com',
            password: 'password123',
            isAdmin: false,
            permissions: ['invoices.view', 'invoices.create']
        );

        $user = $this->service->createUser($dto);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_admin' => false,
        ]);

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertEquals(['invoices.view', 'invoices.create'], $user->permissions);
    }

    /** @test */
    public function it_can_create_admin_user(): void
    {
        $dto = new UserDTO(
            name: 'Admin User',
            email: 'admin@example.com',
            password: 'admin123',
            isAdmin: true
        );

        $user = $this->service->createUser($dto);

        $this->assertTrue($user->is_admin);
    }

    // ==========================================
    // UPDATE USER TESTS
    // ==========================================

    /** @test */
    public function it_can_update_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $updatedUser = $this->service->updateUser($user, [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('New Name', $updatedUser->name);
        $this->assertEquals('new@example.com', $updatedUser->email);
    }

    /** @test */
    public function it_prevents_removing_admin_status_from_last_admin(): void
    {
        // Create only one admin
        $admin = User::factory()->create(['is_admin' => true]);

        $this->expectException(BusinessException::class);

        $this->service->updateUser($admin, ['is_admin' => false]);
    }

    /** @test */
    public function it_allows_removing_admin_when_multiple_admins_exist(): void
    {
        User::factory()->create(['is_admin' => true]);
        $admin2 = User::factory()->create(['is_admin' => true]);

        $updatedUser = $this->service->updateUser($admin2, ['is_admin' => false]);

        $this->assertFalse($updatedUser->is_admin);
    }

    // ==========================================
    // DELETE USER TESTS
    // ==========================================

    /** @test */
    public function it_can_delete_user(): void
    {
        $currentUser = User::factory()->create();
        $userToDelete = User::factory()->create();

        $this->service->deleteUser($userToDelete, $currentUser->id);

        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    /** @test */
    public function it_prevents_self_deletion(): void
    {
        $user = User::factory()->create();

        $this->expectException(BusinessException::class);

        $this->service->deleteUser($user, $user->id);
    }

    /** @test */
    public function it_prevents_deleting_last_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $currentUser = User::factory()->create();

        $this->expectException(BusinessException::class);

        $this->service->deleteUser($admin, $currentUser->id);
    }

    // ==========================================
    // PERMISSIONS TESTS
    // ==========================================

    /** @test */
    public function it_can_update_permissions(): void
    {
        $currentUser = User::factory()->create();
        $targetUser = User::factory()->create(['permissions' => []]);

        $newPermissions = ['invoices.view', 'collections.view'];

        $updatedUser = $this->service->updatePermissions(
            $targetUser,
            $newPermissions,
            $currentUser->id
        );

        $this->assertEquals($newPermissions, $updatedUser->permissions);
    }

    /** @test */
    public function it_prevents_modifying_own_permissions(): void
    {
        $user = User::factory()->create();

        $this->expectException(BusinessException::class);

        $this->service->updatePermissions($user, ['invoices.view'], $user->id);
    }

    // ==========================================
    // PASSWORD TESTS
    // ==========================================

    /** @test */
    public function it_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'failed_login_attempts' => 5,
        ]);

        $this->service->updatePassword($user, 'newpassword123');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
    }

    // ==========================================
    // LOCK/UNLOCK TESTS
    // ==========================================

    /** @test */
    public function it_can_lock_user(): void
    {
        $currentUser = User::factory()->create();
        $targetUser = User::factory()->create(['is_locked' => false]);

        $lockedUser = $this->service->lockUser($targetUser, $currentUser->id);

        $this->assertTrue($lockedUser->is_locked);
        $this->assertNotNull($lockedUser->locked_at);
    }

    /** @test */
    public function it_prevents_locking_self(): void
    {
        $user = User::factory()->create();

        $this->expectException(BusinessException::class);

        $this->service->lockUser($user, $user->id);
    }

    /** @test */
    public function it_can_unlock_user(): void
    {
        $user = User::factory()->create([
            'is_locked' => true,
            'locked_at' => now(),
        ]);

        $unlockedUser = $this->service->unlockUser($user);

        $this->assertFalse($unlockedUser->is_locked);
    }

    // ==========================================
    // LIST USERS TESTS
    // ==========================================

    /** @test */
    public function it_can_list_users_with_filters(): void
    {
        User::factory()->count(3)->create(['is_admin' => true]);
        User::factory()->count(5)->create(['is_admin' => false]);
        User::factory()->count(2)->create(['is_locked' => true]);

        // List all
        $all = $this->service->listUsers();
        $this->assertEquals(10, $all->total());

        // Filter admins
        $admins = $this->service->listUsers(['admin' => true]);
        $this->assertEquals(3, $admins->total());

        // Filter locked
        $locked = $this->service->listUsers(['locked' => true]);
        $this->assertEquals(2, $locked->total());
    }

    /** @test */
    public function it_can_search_users(): void
    {
        User::factory()->create(['name' => 'Ahmed Ali', 'email' => 'ahmed@test.com']);
        User::factory()->create(['name' => 'Mohamed Ahmed', 'email' => 'mohamed@test.com']);
        User::factory()->create(['name' => 'Sara Mohamed', 'email' => 'sara@test.com']);

        $results = $this->service->listUsers(['search' => 'Ahmed']);

        // Should find 2: "Ahmed Ali" and "Mohamed Ahmed"
        $this->assertEquals(2, $results->total());
    }

    // ==========================================
    // PERMISSIONS LIST TESTS
    // ==========================================

    /** @test */
    public function it_returns_valid_permissions_list(): void
    {
        $permissions = $this->service->getValidPermissions();

        $this->assertIsArray($permissions);
        $this->assertContains('invoices.view', $permissions);
        $this->assertContains('users.create', $permissions);
        $this->assertContains('settings.edit', $permissions);
    }

    /** @test */
    public function it_returns_grouped_permissions(): void
    {
        $grouped = $this->service->getGroupedPermissions();

        $this->assertIsArray($grouped);
        $this->assertArrayHasKey('invoices', $grouped);
        $this->assertArrayHasKey('label', $grouped['invoices']);
        $this->assertArrayHasKey('permissions', $grouped['invoices']);
    }
}
