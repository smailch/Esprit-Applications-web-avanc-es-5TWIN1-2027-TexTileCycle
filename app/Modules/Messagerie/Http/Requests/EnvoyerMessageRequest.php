<?php

namespace App\Modules\Messagerie\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnvoyerMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['contenu' => trim((string) $this->input('contenu'))]);
    }

    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contenu.required' => 'Écrivez un message avant d\'envoyer.',
            'contenu.max' => 'Le message ne peut pas dépasser :max caractères.',
        ];
    }
}
