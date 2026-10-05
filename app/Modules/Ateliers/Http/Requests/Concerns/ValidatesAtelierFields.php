<?php

namespace App\Modules\Ateliers\Http\Requests\Concerns;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Rules\HorairesRule;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Support\HorairesFormulaire;
use Closure;

trait ValidatesAtelierFields
{
    /**
     * L'éditeur du back office envoie horaires[jour][ferme|plages] : on le ramène au format de HorairesRule.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('horaires')) {
            $this->merge(['horaires' => HorairesFormulaire::depuisFormulaire($this->input('horaires'))]);
        }
    }

    protected function atelierFieldRules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:120'],
            'specialite' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'adresse' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{6,30}$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'horaires' => ['nullable', 'array', new HorairesRule()],
        ];
    }

    /**
     * Le compte doit exister, avoir le rôle atelier et ne pas être déjà rattaché à un autre atelier.
     */
    protected function userIdRules(?string $ignoreAtelierId = null): array
    {
        return ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($ignoreAtelierId) {
            $erreur = app(AtelierService::class)->verifierCompteAtelier((string) $value, $ignoreAtelierId);

            if ($erreur === AtelierService::COMPTE_INTROUVABLE) {
                $fail('Le compte sélectionné doit exister et avoir le rôle atelier.');
            } elseif ($erreur === AtelierService::COMPTE_DEJA_RATTACHE) {
                $fail('Ce compte est déjà rattaché à un autre atelier.');
            }
        }];
    }

    protected function atelierFieldMessages(): array
    {
        return [
            'user_id.required' => 'Sélectionnez le compte atelier propriétaire.',
            'user_id.string' => 'Le compte atelier est invalide.',
            'nom.required' => 'Le nom de l\'atelier est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 120 caractères.',
            'specialite.max' => 'La spécialité ne doit pas dépasser 120 caractères.',
            'description.max' => 'La description ne doit pas dépasser 2000 caractères.',
            'adresse.required' => 'L\'adresse est obligatoire.',
            'adresse.max' => 'L\'adresse ne doit pas dépasser 255 caractères.',
            'ville.required' => 'La ville est obligatoire.',
            'ville.max' => 'La ville ne doit pas dépasser 100 caractères.',
            'telephone.max' => 'Le téléphone ne doit pas dépasser 30 caractères.',
            'telephone.regex' => 'Le téléphone ne doit contenir que des chiffres, espaces, +, -, ( ou ).',
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'latitude.required_with' => 'La latitude est obligatoire si la longitude est renseignée.',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'longitude.required_with' => 'La longitude est obligatoire si la latitude est renseignée.',
            'horaires.array' => 'Les horaires doivent être un tableau indexé par jour.',
            'statut.required' => 'Choisissez un statut.',
            'statut.in' => 'Choisissez un statut valide : '.implode(', ', array_map([Atelier::class, 'libelleStatut'], Atelier::STATUTS)).'.',
            '*.string' => 'Ce champ doit être un texte.',
        ];
    }
}
