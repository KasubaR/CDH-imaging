<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->withCount(['users', 'examinations'])
            ->orderBy('name')
            ->get();

        return view('admin.departments.index', [
            'departments' => $departments,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('admin.departments.create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'can_send' => $request->boolean('can_send'),
            'can_receive' => $request->boolean('can_receive'),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department created successfully.');
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('admin.departments.edit', [
            'department' => $department,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'can_send' => $request->boolean('can_send'),
            'can_receive' => $request->boolean('can_receive'),
        ]);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department updated successfully.');
    }

    public function deactivate(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department->update(['is_active' => false]);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department deactivated.');
    }

    public function activate(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department->update(['is_active' => true]);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department activated.');
    }
}
