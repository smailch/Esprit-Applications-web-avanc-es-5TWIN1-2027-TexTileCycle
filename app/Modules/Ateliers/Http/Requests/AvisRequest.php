<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Models\Avis;
use App\Modules\Ateliers\Services\AvisService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Avis d'un client. Le droit de noter (rendez-vous terminé) est vérifié par AvisService ; ici, seulement la saisie.
 * Sac d'erreurs dédié : les erreurs s'affichent dans la section Avis, pas en haut de la page.
 */
class AvisRequest extends FormRequest
{
    protected $errorBag = AvisService::BAG;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'integer', 'between:'.Avis::NOTE_MIN.','.Avis::NOTE_MAX],
            'commentaire' => ['nullable', 'string', 'max:'.Avis::COMMENTAIRE_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Choisissez une note de 1 à 5 étoiles.',
            'note.integer' => 'La note doit être un nombre d\'étoiles entre 1 et 5.',
            'note.between' => 'La note doit être comprise entre 1 et 5 étoiles.',
            'commentaire.string' => 'Le commentaire est invalide.',
            'commentaire.max' => 'Le commentaire ne doit pas dépasser '.Avis::COMMENTAIRE_MAX.' caractères.',
        ];
    }

    /** Retour sur la section Avis de la fiche, et non en haut de la page. */
    protected function getRedirectUrl()
    {
        return strtok(parent::getRedirectUrl(), '#').'#avis';
    }
}
