<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof User
            && ($this->user()?->can('update', $account) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $account */
        $account = $this->route('account');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/',
                Rule::unique('users', 'username')->ignore($account->id),
            ],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($account->id),
            ],
            'department_id' => [
                Rule::requiredIf(fn () => $this->input('role') === UserRole::Staff->value),
                'nullable',
                'exists:departments,id',
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
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
