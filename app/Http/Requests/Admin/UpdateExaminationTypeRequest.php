<?php

namespace App\Http\Requests\Admin;

use App\Models\ExaminationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExaminationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('examination_type');

        return $type instanceof ExaminationType
            && ($this->user()?->can('update', $type) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ExaminationType $type */
        $type = $this->route('examination_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('examination_types', 'code')->ignore($type->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
