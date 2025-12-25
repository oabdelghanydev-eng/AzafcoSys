<?php

namespace App\Http\Controllers\Api;

use App\DTOs\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserPasswordRequest;
use App\Http\Requests\Api\UpdateUserPermissionsRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UserController
 *
 * Handles user management with permission enforcement.
 * Delegates business logic to UserService.
 */
/**
 * @tags User
 */
class UserController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;

    public function __construct(
        private UserService $userService
    ) {
    }

    /**
     * List all users
     * Permission: users.view
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->listUsers(
            filters: [
                'locked' => $request->has('locked') ? $request->boolean('locked') : null,
                'admin' => $request->has('admin') ? $request->boolean('admin') : null,
                'search' => $request->search,
            ],
            perPage: $request->per_page ?? 25
        );

        return $this->success($users, 'قائمة المستخدمين');
    }

    /**
     * Get single user
     * Permission: users.view
     */
    public function show(User $user): JsonResponse
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
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $dto = UserDTO::fromRequest($request);
        $user = $this->userService->createUser($dto);

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
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->updateUser($user, $request->validated());

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
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->deleteUser($user, auth()->id());

        return $this->success(null, 'تم حذف المستخدم بنجاح');
    }

    /**
     * Update user permissions
     * Permission: users.edit
     */
    public function updatePermissions(UpdateUserPermissionsRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->updatePermissions(
            $user,
            $request->validated('permissions'),
            auth()->id()
        );

        return $this->success([
            'id' => $user->id,
            'permissions' => $user->permissions,
        ], 'تم تحديث الصلاحيات بنجاح');
    }

    /**
     * Update user password
     * Permission: users.edit
     */
    public function updatePassword(UpdateUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $this->userService->updatePassword($user, $request->validated('password'));

        return $this->success(null, 'تم تحديث كلمة المرور بنجاح');
    }

    /**
     * Lock user account
     * Permission: users.edit
     */
    public function lock(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->lockUser($user, auth()->id());

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
    public function unlock(User $user): JsonResponse
    {
        $this->authorize('unlock', $user);

        $user = $this->userService->unlockUser($user);

        return $this->success([
            'id' => $user->id,
            'is_locked' => false,
        ], 'تم فتح الحساب');
    }

    /**
     * Get list of all valid permissions
     */
    public function permissions(): JsonResponse
    {
        return $this->success([
            'permissions' => $this->userService->getValidPermissions(),
            'grouped' => $this->userService->getGroupedPermissions(),
        ]);
    }
}
