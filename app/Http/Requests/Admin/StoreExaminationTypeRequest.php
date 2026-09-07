<?php

namespace App\Http\Requests\Admin;

use App\Models\ExaminationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExaminationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExaminationType::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('examination_types', 'code')],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
