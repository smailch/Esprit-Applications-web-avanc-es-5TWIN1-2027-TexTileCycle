<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Services\DiagnosticService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Diagnostic par photo : ouvert à tous, comme la recherche d'ateliers (limité en nombre par minute sur la route).
 */
class DiagnosticPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'texte' => ['nullable', 'string', 'max:300'],
            // « Ce n'est pas ça ? » : défaut choisi par le citoyen parmi une liste fermée.
            'defaut' => ['nullable', 'string', Rule::in(array_keys(DiagnosticService::CORRECTIONS))],
            // Position du citoyen (bouton « Utiliser ma position ») : sert à classer les ateliers par distance.
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function position(): ?array
    {
        $donnees = $this->validated();

        return isset($donnees['lat'], $donnees['lng']) ? [(float) $donnees['lat'], (float) $donnees['lng']] : null;
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Ajoutez une photo du vêtement.',
            'photo.file' => 'La photo n\'a pas pu être envoyée. Réessayez.',
            'photo.uploaded' => 'La photo n\'a pas pu être envoyée (5 Mo maximum).',
            'photo.image' => 'La photo doit être une image JPG, PNG ou WebP.',
            'photo.mimes' => 'La photo doit être une image JPG, PNG ou WebP.',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
            'texte.string' => 'La précision doit être un texte.',
            'texte.max' => 'La précision ne doit pas dépasser 300 caractères.',
            'defaut.string' => 'Le défaut choisi est invalide.',
            'defaut.in' => 'Le défaut choisi est invalide.',
            'lat.numeric' => 'La position transmise est invalide.',
            'lat.between' => 'La position transmise est invalide.',
            'lat.required_with' => 'La position transmise est incomplète.',
            'lng.numeric' => 'La position transmise est invalide.',
            'lng.between' => 'La position transmise est invalide.',
            'lng.required_with' => 'La position transmise est incomplète.',
        ];
    }
}
