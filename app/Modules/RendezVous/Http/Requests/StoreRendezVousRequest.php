<?php

namespace App\Modules\RendezVous\Http\Requests;

use App\Modules\RendezVous\Models\RendezVous;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation pour la création d'un rendez-vous.
 *
 * user_id et statut ne sont PAS saisis dans le formulaire :
 * ils sont définis automatiquement dans le contrôleur.
 */
class StoreRendezVousRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'vetement_id' => ['required', 'string'],
            'atelier_id'  => ['required', 'string', Rule::in(array_keys(RendezVous::ATELIERS))],
            'service_id'  => ['nullable', 'string', Rule::in(array_keys(RendezVous::SERVICES))],
            'date_rdv'    => ['required', 'date', 'after:now'],
            'duree'       => ['required', 'integer', 'min:15', 'max:480'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vetement_id.required' => 'Veuillez sélectionner un vêtement.',
            'vetement_id.string'   => 'Le vêtement sélectionné est invalide.',
            'atelier_id.required'  => 'Veuillez sélectionner un atelier.',
            'atelier_id.in'        => 'L\'atelier sélectionné est invalide.',
            'service_id.in'        => 'Le service sélectionné est invalide.',
            'date_rdv.required'    => 'Veuillez indiquer la date et l\'heure du rendez-vous.',
            'date_rdv.date'        => 'La date du rendez-vous n\'est pas valide.',
            'date_rdv.after'       => 'La date du rendez-vous doit être dans le futur.',
            'duree.required'       => 'Veuillez indiquer la durée estimée.',
            'duree.integer'        => 'La durée doit être un nombre entier.',
            'duree.min'            => 'La durée minimale est de 15 minutes.',
            'duree.max'            => 'La durée maximale est de 480 minutes (8 heures).',
            'commentaire.max'      => 'Le commentaire ne doit pas dépasser 1000 caractères.',
        ];
    }
}
