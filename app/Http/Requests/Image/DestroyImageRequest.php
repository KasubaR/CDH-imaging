<?php

namespace App\Http\Requests\Image;

use App\Models\Image;
use Illuminate\Foundation\Http\FormRequest;

class DestroyImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Image $image */
        $image = $this->route('image');

        return $this->user()?->can('delete', $image) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'in:PERMANENTLY DELETE'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Type PERMANENTLY DELETE to confirm.',
            'confirmation.in' => 'Type PERMANENTLY DELETE to confirm.',
        ];
    }
}
