<?php

namespace App\Http\Requests\Management;

use App\Models\FinancialDocument;

class UpdateFinancialDocumentRequest extends FinancialDocumentRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $document = $this->route('documento');
        $type = $document instanceof FinancialDocument ? $document->type : '';

        return $this->commonRules($type);
    }
}
