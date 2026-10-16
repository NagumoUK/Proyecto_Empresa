<?php

namespace App\Http\Requests\Management;

use App\Models\FinancialDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancialDocumentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $document = $this->route('documento');
        $statuses = $document instanceof FinancialDocument
            ? FinancialDocument::TRANSITIONS[$document->type][$document->status] ?? []
            : [];

        return [
            'status' => ['required', Rule::in($statuses)],
        ];
    }
}
