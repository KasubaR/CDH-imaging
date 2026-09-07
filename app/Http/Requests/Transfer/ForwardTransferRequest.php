<?php

namespace App\Http\Requests\Transfer;

use App\Models\Department;
use App\Models\Transfer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForwardTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transfer $transfer */
        $transfer = $this->route('transfer');

        return $this->user()?->can('view', $transfer) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_ids.required' => 'Select at least one destination department.',
            'department_ids.*.exists' => 'One of the selected departments is invalid or inactive.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            /** @var Transfer $transfer */
            $transfer = $this->route('transfer');

            /** @var list<int> $departmentIds */
            $departmentIds = $this->input('department_ids', []);

            $departments = Department::query()->find($departmentIds);

            foreach ($departments as $department) {
                if (! $user->can('forward', [$transfer, $department])) {
                    $validator->errors()->add(
                        'department_ids',
                        "You are not authorized to forward transfers to {$department->name}.",
                    );
                }
            }
        });
    }
}
