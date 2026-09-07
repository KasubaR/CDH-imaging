<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only overview of who holds which permission — the actual assignment
 * action lives on the account edit form (UserController::edit/update); this
 * page exists so an admin doesn't have to open every account to see the
 * whole picture. Admin accounts hold every permission implicitly
 * (User::hasPermission() short-circuits true for them) and have their
 * `permissions` pivot rows cleared to empty on save (see .ai/rules/accounts.md),
 * so they're rendered as "all permissions" rather than reading an empty
 * pivot as "no permissions".
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-permissions');

        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department_id');

        $accounts = User::query()
            ->with(['department', 'permissions'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }))
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.permissions.index', [
            'accounts' => $accounts,
            'departments' => Department::query()->orderBy('name')->get(),
            'permissions' => PermissionEnum::cases(),
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
            ],
        ]);
    }
}
