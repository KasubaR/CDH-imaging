<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for accounts. "Account" is the product/domain term (see
 * CLAUDE.md — "every account is provisioned by an admin"); the underlying
 * model is App\Models\User, so route segments/params say `account` while the
 * class stays UserController, matching how ExaminationTypeController is
 * named after its model.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department_id');
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();

        $accounts = User::query()
            ->with('department')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when(in_array($role, ['admin', 'staff'], true), fn ($query) => $query->where('role', $role))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'role' => $role,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.accounts.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'permissions' => PermissionEnum::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isStaff = $validated['role'] === UserRole::Staff->value;

        $account = User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'department_id' => $validated['department_id'] ?? null,
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        if ($isStaff) {
            $this->syncPermissions($account, $validated['permissions'] ?? []);
        }

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', 'Account created successfully.');
    }

    public function edit(User $account): View
    {
        $this->authorize('update', $account);

        $account->load('permissions');

        return view('admin.accounts.edit', [
            'account' => $account,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'permissions' => PermissionEnum::cases(),
            'assignedPermissionSlugs' => $account->permissions->pluck('slug')->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $account): RedirectResponse
    {
        $validated = $request->validated();
        $isStaff = $validated['role'] === UserRole::Staff->value;

        $isSelf = $account->id === $request->user()->id;

        if ($isSelf && $account->isAdmin() && $isStaff) {
            return back()
                ->withInput()
                ->with('status', 'You cannot remove your own admin access.');
        }

        $account->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'department_id' => $validated['department_id'] ?? null,
            'role' => $validated['role'],
        ]);

        if ($isStaff) {
            $this->syncPermissions($account, $validated['permissions'] ?? []);
        } else {
            $account->permissions()->sync([]);
        }

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', 'Account updated successfully.');
    }

    public function deactivate(Request $request, User $account): RedirectResponse
    {
        $this->authorize('update', $account);

        if ($account->id === $request->user()->id) {
            return back()->with('status', 'You cannot deactivate your own account.');
        }

        $account->update(['is_active' => false]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', 'Account deactivated.');
    }

    public function activate(User $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $account->update(['is_active' => true]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', 'Account activated.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $account): RedirectResponse
    {
        $account->update(['password' => $request->validated()['password']]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', "Password reset for {$account->name}.");
    }

    /**
     * @param  list<string>  $slugs
     */
    private function syncPermissions(User $account, array $slugs): void
    {
        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id');

        $account->permissions()->sync($ids);
    }
}
