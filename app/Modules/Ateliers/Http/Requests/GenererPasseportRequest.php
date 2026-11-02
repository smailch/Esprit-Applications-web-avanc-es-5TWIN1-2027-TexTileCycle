<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Models\Passeport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Génération d'un passeport par l'atelier : la réparation choisie, un mot facultatif sur le travail fait
 * et une garantie facultative. Le droit de générer (RDV terminé de cet atelier) est vérifié par PasseportService.
 */
class GenererPasseportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rendez_vous_id' => ['required', 'string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'mot_atelier' => ['nullable', 'string', 'max:'.Passeport::MOT_MAX],
            'garantie_jours' => ['nullable', 'integer', Rule::in(array_merge([0], Passeport::GARANTIES))],
        ];
    }

    public function messages(): array
    {
        return [
            'rendez_vous_id.required' => 'Choisissez la réparation à certifier.',
            'rendez_vous_id.regex' => 'Cette réparation est introuvable.',
            'mot_atelier.string' => 'Le mot sur la réparation est invalide.',
            'mot_atelier.max' => 'Le mot sur la réparation ne doit pas dépasser '.Passeport::MOT_MAX.' caractères.',
            'garantie_jours.integer' => 'Choisissez une garantie dans la liste.',
            'garantie_jours.in' => 'Choisissez une garantie dans la liste.',
        ];
    }
}
