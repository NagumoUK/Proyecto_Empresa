<?php

namespace App\Http\Requests\Management;

use App\Models\FinancialDocument;
use Illuminate\Validation\Rule;

class StoreFinancialDocumentRequest extends FinancialDocumentRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $type = (string) $this->input('type');

        return [
            'type' => ['required', Rule::in(FinancialDocument::TYPES)],
            ...$this->commonRules($type),
        ];
    }
}
