<?php

namespace App\Services;

use App\DTOs\UserDTO;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

/**
 * UserService
 * 
 * Handles user management business logic including:
 * - CRUD operations
 * - Permission management
 * - Account locking/unlocking
 * 
 * @package App\Services
 */
class UserService extends BaseService
{
    /**
     * List users with filters.
     *
     * @param array $filters Optional filters (locked, admin, search)
     * @param int $perPage Items per page
     * @return LengthAwarePaginator
     */
    public function listUsers(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'is_admin', 'is_locked', 'permissions', 'created_at']);

        if (isset($filters['locked'])) {
            $query->where('is_locked', $filters['locked']);
        }

        if (isset($filters['admin'])) {
            $query->where('is_admin', $filters['admin']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new user.
     *
     * @param UserDTO $dto User data
     * @return User
     */
    public function createUser(UserDTO $dto): User
    {
        return $this->transactionWithLog('Create user', function () use ($dto) {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password ? Hash::make($dto->password) : null,
                'permissions' => $dto->permissions,
                'is_admin' => $dto->isAdmin,
            ]);

            return $user;
        }, ['email' => $dto->email]);
    }

    /**
     * Update a user.
     *
     * @param User $user User to update
     * @param array $data Update data
     * @return User
     * @throws BusinessException If trying to remove last admin
     */
    public function updateUser(User $user, array $data): User
    {
        // Prevent removing last admin
        if (isset($data['is_admin']) && !$data['is_admin'] && $user->is_admin) {
            $this->validateNotLastAdmin($user);
        }

        $user->update($data);

        return $user->fresh();
    }

    /**
     * Delete a user.
     *
     * @param User $user User to delete
     * @param int $currentUserId Current authenticated user ID
     * @throws BusinessException If trying to delete self or last admin
     */
    public function deleteUser(User $user, int $currentUserId): void
    {
        // Cannot delete self
        if ($user->id === $currentUserId) {
            $this->throwBusinessError(
                'USR_002',
                'لا يمكنك حذف نفسك',
                'Cannot delete yourself'
            );
        }

        // Cannot delete last admin
        if ($user->is_admin) {
            $this->validateNotLastAdmin($user);
        }

        $this->transactionWithLog('Delete user', function () use ($user) {
            $user->delete();
        }, ['user_id' => $user->id]);
    }

    /**
     * Update user permissions.
     *
     * @param User $user User to update
     * @param array $permissions New permissions array
     * @param int $currentUserId Current authenticated user ID
     * @return User
     * @throws BusinessException If trying to modify own permissions
     */
    public function updatePermissions(User $user, array $permissions, int $currentUserId): User
    {
        // Cannot modify own permissions
        if ($user->id === $currentUserId) {
            $this->throwBusinessError(
                'USR_005',
                'لا يمكنك تعديل صلاحياتك الخاصة',
                'Cannot modify your own permissions'
            );
        }

        $user->update(['permissions' => $permissions]);

        $this->log('Permissions updated', [
            'user_id' => $user->id,
            'permissions_count' => count($permissions),
        ]);

        return $user->fresh();
    }

    /**
     * Update user password.
     *
     * @param User $user User to update
     * @param string $newPassword New password
     * @return User
     */
    public function updatePassword(User $user, string $newPassword): User
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'failed_login_attempts' => 0,
        ]);

        $this->log('Password updated', ['user_id' => $user->id]);

        return $user;
    }

    /**
     * Lock a user account.
     *
     * @param User $user User to lock
     * @param int $currentUserId Current authenticated user ID
     * @return User
     * @throws BusinessException If trying to lock self
     */
    public function lockUser(User $user, int $currentUserId): User
    {
        if ($user->id === $currentUserId) {
            $this->throwBusinessError(
                'USR_002',
                'لا يمكنك قفل حسابك',
                'Cannot lock your own account'
            );
        }

        $user->lock($currentUserId);

        $this->log('User locked', ['user_id' => $user->id]);

        return $user->fresh();
    }

    /**
     * Unlock a user account.
     *
     * @param User $user User to unlock
     * @return User
     */
    public function unlockUser(User $user): User
    {
        $user->unlock();

        $this->log('User unlocked', ['user_id' => $user->id]);

        return $user->fresh();
    }

    /**
     * Get all valid permission codes.
     *
     * @return array
     */
    public function getValidPermissions(): array
    {
        return [
            // Invoices
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'invoices.cancel',
            // Collections
            'collections.view',
            'collections.create',
            'collections.edit',
            'collections.delete',
            'collections.cancel',
            // Expenses
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            // Shipments
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',
            'shipments.close',
            // Inventory
            'inventory.view',
            'inventory.adjust',
            'inventory.wastage',
            // Cashbox
            'cashbox.view',
            'cashbox.deposit',
            'cashbox.withdraw',
            'cashbox.transfer',
            // Bank
            'bank.view',
            'bank.deposit',
            'bank.withdraw',
            'bank.transfer',
            // Customers
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            // Suppliers
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            // Products
            'products.create',
            'products.edit',
            'products.delete',
            // Reports
            'reports.daily',
            'reports.settlement',
            'reports.customers',
            'reports.suppliers',
            'reports.inventory',
            'reports.export_pdf',
            'reports.export_excel',
            'reports.share',
            // Daily
            'daily.close',
            'daily.reopen',
            // Admin
            'admin.force_close',
            'admin.settings',
            'admin.users',
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.unlock',
            // Settings
            'settings.view',
            'settings.edit',
            // Corrections
            'corrections.approve',
        ];
    }

    /**
     * Get permissions grouped by module.
     *
     * @return array
     */
    public function getGroupedPermissions(): array
    {
        return [
            'invoices' => ['label' => 'الفواتير', 'permissions' => ['view', 'create', 'edit', 'delete', 'cancel']],
            'collections' => ['label' => 'التحصيلات', 'permissions' => ['view', 'create', 'edit', 'delete', 'cancel']],
            'expenses' => ['label' => 'المصروفات', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'shipments' => ['label' => 'الشحنات', 'permissions' => ['view', 'create', 'edit', 'delete', 'close']],
            'inventory' => ['label' => 'المخزون', 'permissions' => ['view', 'adjust', 'wastage']],
            'cashbox' => ['label' => 'الخزنة', 'permissions' => ['view', 'deposit', 'withdraw', 'transfer']],
            'bank' => ['label' => 'البنك', 'permissions' => ['view', 'deposit', 'withdraw', 'transfer']],
            'customers' => ['label' => 'العملاء', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'suppliers' => ['label' => 'الموردين', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'products' => ['label' => 'الأصناف', 'permissions' => ['create', 'edit', 'delete']],
            'reports' => ['label' => 'التقارير', 'permissions' => ['daily', 'settlement', 'customers', 'suppliers', 'inventory', 'export_pdf', 'export_excel', 'share']],
            'daily' => ['label' => 'إغلاق اليوم', 'permissions' => ['close', 'reopen']],
            'admin' => ['label' => 'صلاحيات إدارية', 'permissions' => ['force_close', 'settings', 'users']],
            'users' => ['label' => 'المستخدمين', 'permissions' => ['view', 'create', 'edit', 'delete', 'unlock']],
            'settings' => ['label' => 'الإعدادات', 'permissions' => ['view', 'edit']],
            'corrections' => ['label' => 'التصحيحات', 'permissions' => ['approve']],
        ];
    }

    /**
     * Validate that the user is not the last admin.
     *
     * @throws BusinessException If user is the last admin
     */
    protected function validateNotLastAdmin(User $user): void
    {
        if (!$user->is_admin) {
            return;
        }

        $adminCount = User::where('is_admin', true)->count();

        if ($adminCount <= 1) {
            $this->throwBusinessError(
                'USR_003',
                'لا يمكن إزالة صلاحية Admin من آخر مسؤول',
                'Cannot remove admin status from last admin'
            );
        }
    }

    protected function getServiceName(): string
    {
        return 'UserService';
    }
}
