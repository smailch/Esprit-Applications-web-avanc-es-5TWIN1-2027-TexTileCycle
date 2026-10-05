<?php

namespace App\Modules\RendezVous\Http\Requests;

use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatutRendezVousRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in([
                RendezVous::STATUT_CONFIRME,
                RendezVous::STATUT_REFUSE,
                RendezVous::STATUT_TERMINE,
                RendezVous::STATUT_ANNULE,
            ])],
            'motif' => ['nullable', 'string', 'max:'.RendezVousService::MOTIF_MAX, 'required_if:statut,'.RendezVous::STATUT_REFUSE],
        ];
    }

    public function messages(): array
    {
        return [
            'statut.required' => 'Choisissez une action.',
            'statut.in' => "Cette action n'existe pas.",
            'motif.required_if' => 'Indiquez le motif du refus.',
            'motif.string' => 'Le motif est invalide.',
            'motif.max' => 'Le motif ne peut pas dépasser '.RendezVousService::MOTIF_MAX.' caractères.',
        ];
    }
}
