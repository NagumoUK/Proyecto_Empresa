<?php

namespace App\Http\Requests\Management;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('proyecto') instanceof Project;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp,txt',
                'extensions:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp,txt',
                'max:20480',
            ],
        ];
    }
}
