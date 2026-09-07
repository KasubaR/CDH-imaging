<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Patient::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:255'],
            'nrc' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'nrc')],
        ];
    }
}
