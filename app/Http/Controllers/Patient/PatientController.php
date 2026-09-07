<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Patient::class);

        $search = $request->string('search')->trim()->toString();

        $patients = Patient::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('patient_name', 'like', "%{$search}%")
                        ->orWhere('nrc', 'like', "%{$search}%");
                });
            })
            ->withCount('examinations')
            ->orderBy('patient_name')
            ->paginate(15)
            ->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Patient::class);

        return view('patients.create');
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = Patient::query()->create($request->validated());

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', 'Patient registered successfully.');
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);

        $patient->load([
            'examinations' => fn ($query) => $query
                ->with(['examinationType', 'referringDepartment'])
                ->orderByDesc('date_taken')
                ->orderByDesc('time_taken'),
        ]);

        return view('patients.show', [
            'patient' => $patient,
        ]);
    }
}
