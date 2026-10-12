<?php

namespace App\Modules\Ateliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recherche de la liste admin. Le statut vient d'une liste déroulante : une valeur inconnue
 * est simplement ignorée par le contrôleur.
 */
class FiltreAteliersAdminRequest extends FormRequest
{
    /**
     * Requête GET : revenir à "l'URL précédente" pourrait boucler sur l'URL invalide elle-même.
     */
    protected $redirectRoute = 'back.ateliers';

    /**
     * Les droits sont vérifiés dans le contrôleur (redirection du rôle atelier, accès admin).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'La recherche doit être un texte.',
            'q.max' => 'La recherche ne doit pas dépasser 100 caractères.',
        ];
    }
}
