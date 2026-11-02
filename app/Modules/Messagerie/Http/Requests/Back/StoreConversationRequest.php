<?php

namespace App\Modules\Messagerie\Http\Requests\Back;

use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Http\Requests\StoreConversationRequest as BaseRequest;
use App\Modules\Messagerie\Models\Conversation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Nouvelle conversation ouverte par l'administration vers un citoyen ou un partenaire,
 * éventuellement rattachée à un signalement, un atelier ou une association.
 */
class StoreConversationRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return parent::rules() + [
            'user_id' => ['required', 'string'],
            'contexte_type' => ['nullable', Rule::in(array_keys(Conversation::CONTEXTES))],
            'contexte_id' => ['nullable', 'required_with:contexte_type', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'user_id.required' => 'Choisissez le destinataire.',
            'contexte_type.in' => 'Contexte invalide.',
            'contexte_id.required_with' => 'Le contexte de la conversation est incomplet.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('user_id')) {
                $destinataire = User::find($this->input('user_id'));

                if (! $destinataire || $destinataire->isAdmin()) {
                    $validator->errors()->add('user_id', 'Ce destinataire n\'est pas valide.');
                }
            }

            if ($this->filled('contexte_type') && $this->filled('contexte_id') && ! $validator->errors()->has('contexte_type')) {
                $modele = Conversation::CONTEXTES[$this->input('contexte_type')];

                if (! $modele::find($this->input('contexte_id'))) {
                    $validator->errors()->add('contexte_id', 'L\'élément lié à cette conversation est introuvable.');
                }
            }
        });
    }

    public function destinataire(): User
    {
        return User::findOrFail($this->validated('user_id'));
    }
}
