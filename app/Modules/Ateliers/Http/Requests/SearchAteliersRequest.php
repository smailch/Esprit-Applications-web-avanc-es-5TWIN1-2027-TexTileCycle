<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Services\AtelierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recherche publique. rayon_km et tri=distance sont ignorés par le service sans lat/lng.
 */
class SearchAteliersRequest extends FormRequest
{
    /**
     * Requête GET : revenir à "l'URL précédente" pourrait boucler sur l'URL invalide elle-même.
     */
    protected $redirectRoute = 'front.ateliers';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:120'],
            'ville' => ['nullable', 'string', 'max:100'],
            'note_min' => ['nullable', 'numeric', 'between:0,5'],
            'rayon_km' => ['nullable', 'numeric', 'between:1,100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'tri' => ['nullable', Rule::in(AtelierService::TRIS)],
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'La recherche doit être un texte.',
            'q.max' => 'La recherche ne doit pas dépasser 100 caractères.',
            'service.string' => 'Le service sélectionné est invalide.',
            'service.max' => 'Le service sélectionné est invalide.',
            'ville.string' => 'La ville sélectionnée est invalide.',
            'ville.max' => 'La ville sélectionnée est invalide.',
            'note_min.numeric' => 'La note minimale doit être un nombre.',
            'note_min.between' => 'La note minimale doit être comprise entre 0 et 5.',
            'rayon_km.numeric' => 'Le rayon doit être un nombre de kilomètres.',
            'rayon_km.between' => 'Le rayon doit être compris entre 1 et 100 km.',
            'lat.numeric' => 'La latitude doit être un nombre.',
            'lat.between' => 'La latitude doit être comprise entre -90 et 90.',
            'lat.required_with' => 'La latitude est obligatoire avec la longitude.',
            'lng.numeric' => 'La longitude doit être un nombre.',
            'lng.between' => 'La longitude doit être comprise entre -180 et 180.',
            'lng.required_with' => 'La longitude est obligatoire avec la latitude.',
            'tri.in' => 'Le tri doit être : '.implode(', ', AtelierService::TRIS).'.',
        ];
    }
}
