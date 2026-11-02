<?php

namespace App\Modules\Messagerie\Http\Requests;

use App\Modules\Messagerie\Services\MessagerieService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Nouvelle conversation ouverte par un citoyen (ou un partenaire) vers l'équipe TexTileCycle,
 * un atelier ou une association, choisi avec la recherche de destinataire.
 */
class StoreConversationRequest extends FormRequest
{
    private ?array $destinataireResolu = null;

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
            'destinataire' => ['required', 'string', 'max:80'],
            'sujet' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'confirmer' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'destinataire.required' => 'Choisissez à qui vous écrivez (tapez au moins 3 caractères puis sélectionnez un résultat).',
            'sujet.required' => 'Indiquez le sujet de votre message.',
            'sujet.min' => 'Le sujet doit contenir au moins :min caractères.',
            'sujet.max' => 'Le sujet ne peut pas dépasser :max caractères.',
            'message.required' => 'Écrivez votre message.',
            'message.max' => 'Le message ne peut pas dépasser :max caractères.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('destinataire')) {
                return;
            }

            $this->destinataireResolu = app(MessagerieService::class)
                ->resoudreDestinataire($this->input('destinataire'), $this->user());

            if (! $this->destinataireResolu) {
                $validator->errors()->add('destinataire', 'Ce destinataire n\'est pas disponible. Choisissez-en un autre dans la liste.');
            }
        });
    }

    /**
     * @return array{partenaire: ?\App\Modules\Auth\Models\User, contexte: ?\Illuminate\Database\Eloquent\Model, nom: string}
     */
    public function destinataire(): array
    {
        return $this->destinataireResolu;
    }
}
