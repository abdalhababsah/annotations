<?php

namespace App\Http\Requests\Admin\AudioFile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAudioFileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'original_filename' => ['nullable','string','max:500'],
            'metadata' => ['nullable','array'],
        ];
    }
}
