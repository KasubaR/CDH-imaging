<?php

namespace App\Http\Requests\Image;

use App\Models\Examination;
use App\Models\Image;
use Illuminate\Foundation\Http\FormRequest;

class StoreUploadChunkRequest extends FormRequest
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
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:50'],
            'filename' => ['required', 'string', 'max:255'],
            'chunk' => [
                'required',
                'file',
                'max:'.intdiv((int) config('cdh.upload_chunk_max_bytes'), 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chunk.max' => 'One chunk of the upload exceeded the allowed chunk size.',
        ];
    }
}
