<?php
// app/Http/Requests/BaseFormRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

abstract class BaseFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        // High-level banner; detailed messages still in $errors bag
        session()->flash('error', 'Please fix the highlighted fields.');
        parent::failedValidation($validator);
    }
}
