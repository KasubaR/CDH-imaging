<?php

namespace App\Http\Controllers\Examination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\StoreExaminationRequest;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Transfer;
use App\Services\TransferAuthorizationService;
use App\Services\TransferLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExaminationController extends Controller
{
    public function __construct(
        private readonly TransferLifecycleService $transferLifecycle,
        private readonly TransferAuthorizationService $transferAuthorization,
    ) {
        //
    }

    public function create(Patient $patient): View
    {
        $this->authorize('create', Examination::class);
        $this->authorize('view', $patient);

        $examinationTypes = ExaminationType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $user = auth()->user();

        return view('examinations.create', [
            'patient' => $patient,
            'examinationTypes' => $examinationTypes,
            'departments' => $departments,
            'defaultDepartmentId' => $user?->department_id,
        ]);
    }

    public function store(StoreExaminationRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('view', $patient);

        $examination = $patient->examinations()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('examinations.show', $examination)
            ->with('status', 'Examination recorded successfully.');
    }

    public function show(Examination $examination): View|RedirectResponse
    {
        $this->authorize('view', $examination);

        $user = auth()->user();

        if ($user !== null) {
            if ($this->transferLifecycle->departmentMustAcknowledge($examination, $user)) {
                return redirect()
                    ->route('department.index')
                    ->with('status', 'Acknowledge receipt of this transfer before opening it.');
            }

            $this->transferLifecycle->trackExaminationViewed($examination, $user);
        }

        $examination->load([
            'patient',
            'examinationType',
            'referringDepartment',
            'createdBy',
            'images',
            'transfers.recipients.department',
            'transfers.fromDepartment',
        ]);

        return view('examinations.show', [
            'examination' => $examination,
            'sendDestinations' => $user !== null
                ? $this->transferAuthorization->allowedDestinationsFor($user)
                : collect(),
            'forwardDestinations' => $user !== null
                ? $this->transferAuthorization->allowedForwardDestinationsFor($user)
                : collect(),
            'inboundTransfer' => $user !== null
                ? Transfer::query()
                    ->where('examination_id', $examination->id)
                    ->whereHas('recipients', fn ($query) => $query->where('department_id', $user->department_id))
                    ->latest('id')
                    ->first()
                : null,
            'outboundTransfers' => $user !== null
                ? $examination->transfers
                    ->where('from_department_id', $user->department_id)
                    ->values()
                : collect(),
        ]);
    }
}
