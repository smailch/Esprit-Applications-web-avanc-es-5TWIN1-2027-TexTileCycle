<?php

namespace App\Modules\Signalements\Http\Requests\Back;

use App\Modules\Signalements\Http\Requests\StoreSignalementRequest as BaseRequest;
use App\Modules\Signalements\Models\Signalement;
use Illuminate\Validation\Rule;

class UpdateSignalementRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['required', Rule::in(Signalement::TYPES)],
            'statut' => ['required', Rule::in(Signalement::STATUTS)],
            'note_admin' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $this->input('statut') === Signalement::STATUT_REJETE)],
        ]);
    }

    public function messages(): array
    {
        return parent::messages() + [
            'type.required' => 'Choisissez le type de signalement.',
            'statut.required' => 'Choisissez un statut.',
            'statut.in' => 'Statut invalide.',
            'note_admin.required' => 'Expliquez pourquoi le signalement est rejeté.',
            'note_admin.max' => 'La note ne peut pas dépasser :max caractères.',
        ];
    }
}
