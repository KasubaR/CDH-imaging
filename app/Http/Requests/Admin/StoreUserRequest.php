<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'department_id' => [
                Rule::requiredIf(fn () => $this->input('role') === UserRole::Staff->value),
                'nullable',
                'exists:departments,id',
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(PermissionEnum::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'A department account needs a department.',
            'username.regex' => 'Username may only contain letters, numbers, dots, dashes and underscores.',
        ];
    }
}
