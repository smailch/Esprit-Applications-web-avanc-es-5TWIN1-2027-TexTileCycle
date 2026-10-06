<?php

namespace App\Modules\Ateliers\Http\Requests\Concerns;

use App\Modules\Auth\Models\User;

/**
 * La propriété du service (atelier_id) est vérifiée par ServicePolicy et ServiceCatalogueService.
 */
trait ValidatesServiceFields
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [User::ROLE_ADMIN, User::ROLE_ATELIER], true);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'prix_estime' => ['required', 'numeric', 'min:0', 'max:100000'],
            'duree_estimee' => ['required', 'integer', 'between:5,480'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du service est obligatoire.',
            'nom.string' => 'Le nom du service doit être un texte.',
            'nom.max' => 'Le nom du service ne doit pas dépasser 120 caractères.',
            'description.string' => 'La description doit être un texte.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'prix_estime.required' => 'Le prix estimé est obligatoire.',
            'prix_estime.numeric' => 'Le prix estimé doit être un nombre.',
            'prix_estime.min' => 'Le prix estimé ne peut pas être négatif.',
            'prix_estime.max' => 'Le prix estimé est trop élevé.',
            'duree_estimee.required' => 'La durée estimée est obligatoire.',
            'duree_estimee.integer' => 'La durée estimée doit être un nombre entier de minutes.',
            'duree_estimee.between' => 'La durée estimée doit être comprise entre 5 et 480 minutes.',
        ];
    }
}
