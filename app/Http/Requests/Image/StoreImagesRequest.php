<?php

namespace App\Http\Requests\Image;

use App\Models\Examination;
use App\Models\Image;
use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class StoreImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Examination $examination */
        $examination = $this->route('examination');

        return $this->user()?->can('upload', [Image::class, $examination]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:50'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
                'max:'.intdiv(app(SettingsService::class)->get('image_max_bytes'), 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Select at least one image to upload.',
            'images.*.mimes' => 'Only JPG, JPEG and PNG files are supported.',
            'images.*.mimetypes' => 'Only JPG, JPEG and PNG files are supported.',
            'images.*.max' => 'Each file must be 15 MB or smaller.',
        ];
    }
}
