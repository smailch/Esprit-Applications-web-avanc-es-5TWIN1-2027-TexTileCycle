<?php

namespace App\Modules\Vetements\Http\Requests;

use App\Modules\Vetements\Models\Vetement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVetementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:80'],
            'size' => ['required', 'string', 'max:20'],
            'condition_label' => ['required', 'string', 'in:Excellent,Bon état,À réparer,Abîmé'],
            'material' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'intended_action' => ['required', Rule::in(Vetement::INTENDED_ACTIONS)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Choisissez un type de vêtement.',
            'size.required' => 'Choisissez une taille.',
            'material.required' => 'Indiquez la matière (liste ou saisie libre).',
            'condition_label.required' => 'Indiquez l\'état du vêtement.',
            'intended_action.required' => 'Choisissez si vous souhaitez réparer ou faire un don.',
            'photo.image' => 'La photo doit être une image (JPG, PNG ou WebP).',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
        ];
    }
}
