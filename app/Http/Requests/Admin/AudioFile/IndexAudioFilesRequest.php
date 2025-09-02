<?php

namespace App\Http\Requests\Admin\AudioFile;

use Illuminate\Foundation\Http\FormRequest;

class IndexAudioFilesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'q' => ['nullable','string','max:200'],
            'uploader' => ['nullable','integer'],
            'mime' => ['nullable','string','max:100'],
            'date_from' => ['nullable','date'],
            'date_to' => ['nullable','date','after_or_equal:date_from'],
            'size_min' => ['nullable','integer','min:0'],
            'size_max' => ['nullable','integer','gte:size_min'],
            'dur_min' => ['nullable','numeric','min:0'],
            'dur_max' => ['nullable','numeric','gte:dur_min'],
            'sort' => ['nullable','in:created_at,original_filename,file_size,duration'],
            'direction' => ['nullable','in:asc,desc'],
            'per_page' => ['nullable','integer','min:5','max:100'],
        ];
    }

    public function filters(): array
    {
        return $this->validated();
    }
}
