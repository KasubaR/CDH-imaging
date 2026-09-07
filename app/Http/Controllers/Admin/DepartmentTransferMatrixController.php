<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The from->to routing matrix that TransferAuthorizationService actually
 * checks (App\Models\DepartmentPermission) — separate from the basic
 * Department CRUD in DepartmentController, since this edits a grid across
 * every active department rather than one record.
 */
class DepartmentTransferMatrixController extends Controller
{
    public function edit(): View
    {
        $this->authorize('manage-departments');

        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();

        $enabledPairs = DepartmentPermission::query()
            ->where('can_send', true)
            ->where('can_receive', true)
            ->get(['from_department_id', 'to_department_id'])
            ->map(fn (DepartmentPermission $permission) => $permission->from_department_id.':'.$permission->to_department_id)
            ->flip();

        return view('admin.departments.matrix', [
            'departments' => $departments,
            'enabledPairs' => $enabledPairs,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-departments');

        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $submitted = $request->input('matrix', []);

        DB::transaction(function () use ($departments, $submitted): void {
            foreach ($departments as $from) {
                foreach ($departments as $to) {
                    if ($from->id === $to->id) {
                        continue;
                    }

                    $enabled = (bool) ($submitted[$from->id][$to->id] ?? false);

                    if ($enabled) {
                        DepartmentPermission::query()->updateOrCreate(
                            ['from_department_id' => $from->id, 'to_department_id' => $to->id],
                            ['can_send' => true, 'can_receive' => true],
                        );
                    } else {
                        DepartmentPermission::query()
                            ->where('from_department_id', $from->id)
                            ->where('to_department_id', $to->id)
                            ->delete();
                    }
                }
            }
        });

        return redirect()
            ->route('admin.departments.matrix')
            ->with('status', 'Transfer matrix updated successfully.');
    }
}
