<?php

namespace App\Modules\Messagerie\Http\Requests\Back;

use App\Modules\Messagerie\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sujet' => trim((string) $this->input('sujet'))]);
    }

    public function rules(): array
    {
        return [
            'sujet' => ['required', 'string', 'min:3', 'max:150'],
            'statut' => ['required', Rule::in(array_keys(Conversation::STATUTS))],
        ];
    }

    public function messages(): array
    {
        return [
            'sujet.required' => 'Le sujet est obligatoire.',
            'sujet.min' => 'Le sujet doit contenir au moins :min caractères.',
            'sujet.max' => 'Le sujet ne peut pas dépasser :max caractères.',
            'statut.required' => 'Choisissez un statut.',
            'statut.in' => 'Statut invalide.',
        ];
    }
}
