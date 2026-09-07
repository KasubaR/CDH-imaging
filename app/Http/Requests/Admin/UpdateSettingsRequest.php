<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image_retention_months' => ['required', 'integer', 'min:1', 'max:120'],
            'image_max_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'upload_session_ttl_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'backup_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }
}
