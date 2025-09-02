<?php

namespace App\Http\Requests\Admin\AudioFile;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioFilesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'files'   => ['required','array','min:1'],
            'files.*' => [
    'file',
    'mimes:mp3,wav,flac,aac,ogg,webm',
    'max:204800'
],

        ];
    }
}
