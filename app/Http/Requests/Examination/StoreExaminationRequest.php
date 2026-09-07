<?php

namespace App\Http\Requests\Examination;

use App\Models\Examination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Examination::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'examination_type_id' => [
                'required',
                Rule::exists('examination_types', 'id')->where('is_active', true),
            ],
            'body_part' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'date_taken' => ['required', 'date'],
            'time_taken' => ['required', 'date_format:H:i'],
            'referring_department_id' => ['required', Rule::exists('departments', 'id')],
            'referring_clinician' => ['nullable', 'string', 'max:255'],
            'radiographer' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'examination_type_id.exists' => 'The selected examination type is invalid or inactive.',
        ];
    }
}
