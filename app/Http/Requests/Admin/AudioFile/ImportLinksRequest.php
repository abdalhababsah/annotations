<?php

namespace App\Http\Requests\Admin\AudioFile;

use App\Http\Requests\BaseFormRequest;

class ImportLinksRequest extends BaseFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Either upload a CSV/XLSX file OR paste links in a textarea
            'file' => ['nullable','file','mimetypes:text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'links' => ['nullable','string'],
        ];
    }
}
