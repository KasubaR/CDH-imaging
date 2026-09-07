<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExaminationTypeRequest;
use App\Http\Requests\Admin\UpdateExaminationTypeRequest;
use App\Models\ExaminationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExaminationTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ExaminationType::class);

        $examinationTypes = ExaminationType::query()
            ->withCount('examinations')
            ->orderBy('name')
            ->get();

        return view('admin.examination-types.index', [
            'examinationTypes' => $examinationTypes,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ExaminationType::class);

        return view('admin.examination-types.create');
    }

    public function store(StoreExaminationTypeRequest $request): RedirectResponse
    {
        ExaminationType::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.examination-types.index')
            ->with('status', 'Examination type created successfully.');
    }

    public function edit(ExaminationType $examinationType): View
    {
        $this->authorize('update', $examinationType);

        return view('admin.examination-types.edit', [
            'examinationType' => $examinationType,
        ]);
    }

    public function update(UpdateExaminationTypeRequest $request, ExaminationType $examinationType): RedirectResponse
    {
        $examinationType->update($request->validated());

        return redirect()
            ->route('admin.examination-types.index')
            ->with('status', 'Examination type updated successfully.');
    }

    public function deactivate(ExaminationType $examinationType): RedirectResponse
    {
        $this->authorize('update', $examinationType);

        $examinationType->update(['is_active' => false]);

        return redirect()
            ->route('admin.examination-types.index')
            ->with('status', 'Examination type deactivated.');
    }

    public function activate(ExaminationType $examinationType): RedirectResponse
    {
        $this->authorize('update', $examinationType);

        $examinationType->update(['is_active' => true]);

        return redirect()
            ->route('admin.examination-types.index')
            ->with('status', 'Examination type activated.');
    }
}
