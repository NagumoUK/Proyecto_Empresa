<?php

namespace App\Http\Requests\Management;

use App\Models\FinancialDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFinancialDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(FinancialDocument::TYPES)],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'approved', 'rejected', 'paid', 'cancelled'])],
        ];
    }
}
