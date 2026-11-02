<?php

namespace App\Modules\Vetements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscuterVetementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:500'],
            'historique' => ['nullable', 'array', 'max:6'],
            'historique.*.role' => ['required_with:historique', 'in:user,assistant'],
            'historique.*.contenu' => ['required_with:historique', 'string', 'max:700'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Écrivez une question sur un vêtement.',
            'message.min' => 'La question est trop courte.',
            'message.max' => 'La question est limitée à 500 caractères.',
            'historique.max' => 'La conversation affichée est trop longue. Relancez le conseiller.',
        ];
    }
}
