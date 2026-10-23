<?php

namespace App\Http\Requests\Management;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $client = $this->route('cliente');

        return [
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:180'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->ignore($client instanceof Client ? $client->id : null),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => [
                'bail',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:4096',
                'dimensions:max_width=4096,max_height=4096',
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
