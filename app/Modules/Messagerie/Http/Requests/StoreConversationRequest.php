<?php

namespace App\Modules\Messagerie\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nouvelle conversation ouverte par un citoyen ou un partenaire vers l'équipe d'administration.
 */
class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sujet' => trim((string) $this->input('sujet')),
            'message' => trim((string) $this->input('message')),
        ]);
    }

    public function rules(): array
    {
        return [
            'sujet' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sujet.required' => 'Indiquez le sujet de votre message.',
            'sujet.min' => 'Le sujet doit contenir au moins :min caractères.',
            'sujet.max' => 'Le sujet ne peut pas dépasser :max caractères.',
            'message.required' => 'Écrivez votre message.',
            'message.max' => 'Le message ne peut pas dépasser :max caractères.',
        ];
    }
}
