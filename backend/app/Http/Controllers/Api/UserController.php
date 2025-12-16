<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * UserController
 * 
 * Handles user management with permission enforcement
 */
/**
 * @tags User
 */
class UserController extends Controller
{
    use ApiResponse;

    /**
     * List all users
     * Permission: users.view
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->select(['id', 'name', 'email', 'is_admin', 'is_locked', 'permissions', 'created_at']);

        // Filter by status
        if ($request->has('locked')) {
            $query->where('is_locked', $request->boolean('locked'));
        }

        // Filter by admin
        if ($request->has('admin')) {
            $query->where('is_admin', $request->boolean('admin'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate($request->per_page ?? 25);

        return $this->success($users, 'قائمة المستخدمين');
    }

    /**
     * Get single user
     * Permission: users.view
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_locked' => $user->is_locked,
            'locked_at' => $user->locked_at,
            'permissions' => $user->permissions,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Create new user
     * Permission: users.create
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($this->getValidPermissions())],
            'is_admin' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'permissions' => $validated['permissions'] ?? [],
            'is_admin' => $validated['is_admin'] ?? false,
        ]);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 'تم إنشاء المستخدم بنجاح', 201);
    }

    /**
     * Update user
     * Permission: users.edit
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => 'boolean',
        ]);

        // Prevent removing last admin
        if (isset($validated['is_admin']) && !$validated['is_admin'] && $user->is_admin) {
            $adminCount = User::where('is_admin', true)->count();
            if ($adminCount <= 1) {
                throw new BusinessException(
                    'USR_003',
                    'لا يمكن إزالة صلاحية Admin من آخر مسؤول',
                    'Cannot remove admin status from last admin'
                );
            }
        }

        $user->update($validated);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
        ], 'تم تحديث المستخدم بنجاح');
    }

    /**
     * Delete user
     * Permission: users.delete
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Cannot delete self
        if ($user->id === auth()->id()) {
            throw new BusinessException(
                'USR_002',
                'لا يمكنك حذف نفسك',
                'Cannot delete yourself'
            );
        }

        // Cannot delete last admin
        if ($user->is_admin) {
            $adminCount = User::where('is_admin', true)->count();
            if ($adminCount <= 1) {
                throw new BusinessException(
                    'USR_003',
                    'لا يمكن حذف آخر مسؤول',
                    'Cannot delete last admin'
                );
            }
        }

        $user->delete();

        return $this->success(null, 'تم حذف المستخدم بنجاح');
    }

    /**
     * Update user permissions
     * Permission: users.edit
     */
    public function updatePermissions(Request $request, User $user)
    {
        $this->authorize('update', $user);

        // Cannot modify own permissions
        if ($user->id === auth()->id()) {
            throw new BusinessException(
                'USR_005',
                'لا يمكنك تعديل صلاحياتك الخاصة',
                'Cannot modify your own permissions'
            );
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => ['string', Rule::in($this->getValidPermissions())],
        ]);

        $user->update(['permissions' => $validated['permissions']]);

        return $this->success([
            'id' => $user->id,
            'permissions' => $user->permissions,
        ], 'تم تحديث الصلاحيات بنجاح');
    }

    /**
     * Update user password
     * Permission: users.edit
     */
    public function updatePassword(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'failed_login_attempts' => 0,
        ]);

        return $this->success(null, 'تم تحديث كلمة المرور بنجاح');
    }

    /**
     * Lock user account
     * Permission: users.edit
     */
    public function lock(User $user)
    {
        $this->authorize('update', $user);

        // Cannot lock self
        if ($user->id === auth()->id()) {
            throw new BusinessException(
                'USR_002',
                'لا يمكنك قفل حسابك',
                'Cannot lock your own account'
            );
        }

        $user->lock(auth()->id());

        return $this->success([
            'id' => $user->id,
            'is_locked' => true,
            'locked_at' => $user->locked_at,
        ], 'تم قفل الحساب');
    }

    /**
     * Unlock user account
     * Permission: users.unlock
     */
    public function unlock(User $user)
    {
        $this->authorize('unlock', $user);

        $user->unlock();

        return $this->success([
            'id' => $user->id,
            'is_locked' => false,
        ], 'تم فتح الحساب');
    }

    /**
     * Get list of all valid permissions
     */
    public function permissions()
    {
        return $this->success([
            'permissions' => $this->getValidPermissions(),
            'grouped' => $this->getGroupedPermissions(),
        ]);
    }

    /**
     * Get all valid permission codes
     */
    private function getValidPermissions(): array
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
     * Get permissions grouped by module
     */
    private function getGroupedPermissions(): array
    {
        return [
            'invoices' => [
                'label' => 'الفواتير',
                'permissions' => ['view', 'create', 'edit', 'delete', 'cancel'],
            ],
            'collections' => [
                'label' => 'التحصيلات',
                'permissions' => ['view', 'create', 'edit', 'delete', 'cancel'],
            ],
            'expenses' => [
                'label' => 'المصروفات',
                'permissions' => ['view', 'create', 'edit', 'delete'],
            ],
            'shipments' => [
                'label' => 'الشحنات',
                'permissions' => ['view', 'create', 'edit', 'delete', 'close'],
            ],
            'inventory' => [
                'label' => 'المخزون',
                'permissions' => ['view', 'adjust', 'wastage'],
            ],
            'cashbox' => [
                'label' => 'الخزنة',
                'permissions' => ['view', 'deposit', 'withdraw', 'transfer'],
            ],
            'bank' => [
                'label' => 'البنك',
                'permissions' => ['view', 'deposit', 'withdraw', 'transfer'],
            ],
            'customers' => [
                'label' => 'العملاء',
                'permissions' => ['view', 'create', 'edit', 'delete'],
            ],
            'suppliers' => [
                'label' => 'الموردين',
                'permissions' => ['view', 'create', 'edit', 'delete'],
            ],
            'products' => [
                'label' => 'الأصناف',
                'permissions' => ['create', 'edit', 'delete'],
            ],
            'reports' => [
                'label' => 'التقارير',
                'permissions' => ['daily', 'settlement', 'customers', 'suppliers', 'inventory', 'export_pdf', 'export_excel', 'share'],
            ],
            'daily' => [
                'label' => 'إغلاق اليوم',
                'permissions' => ['close', 'reopen'],
            ],
            'users' => [
                'label' => 'المستخدمين',
                'permissions' => ['view', 'create', 'edit', 'delete', 'unlock'],
            ],
            'settings' => [
                'label' => 'الإعدادات',
                'permissions' => ['view', 'edit'],
            ],
            'corrections' => [
                'label' => 'التصحيحات',
                'permissions' => ['approve'],
            ],
        ];
    }
}
