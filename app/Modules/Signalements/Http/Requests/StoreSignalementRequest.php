<?php

namespace App\Modules\Signalements\Http\Requests;

use App\Modules\Signalements\Models\Signalement;
use App\Modules\Signalements\Services\SignalementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Signalement émis par un citoyen (front office) ou saisi par l'administration.
 * Le champ `cible` ("Type|id", liste déroulante) est éclaté en cible_type / cible_id.
 */
class StoreSignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cible')) && str_contains($this->input('cible'), '|')) {
            [$type, $id] = explode('|', $this->input('cible'), 2);
            $this->merge(['cible_type' => $type, 'cible_id' => $id]);
        }

        if ($this->filled('motif')) {
            $this->merge(['motif' => trim($this->input('motif'))]);
        }
    }

    public function rules(): array
    {
        return [
            'cible_type' => ['required', Rule::in(array_keys(Signalement::CIBLES))],
            'cible_id' => ['required', 'string', 'max:64'],
            'type' => ['nullable', Rule::in(Signalement::TYPES)],
            'motif' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cible_type.required' => 'Choisissez l\'élément à signaler.',
            'cible_type.in' => 'Ce type d\'élément ne peut pas être signalé.',
            'cible_id.required' => 'Choisissez l\'élément à signaler.',
            'type.in' => 'Type de signalement invalide.',
            'motif.required' => 'Merci de préciser le motif du signalement.',
            'motif.min' => 'Le motif doit contenir au moins :min caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser :max caractères.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('cible_type') || $validator->errors()->has('cible_id')) {
                return;
            }

            if (! app(SignalementService::class)->cibleExiste($this->input('cible_type'), $this->input('cible_id'))) {
                $validator->errors()->add('cible_id', "L'élément signalé est introuvable.");
            }
        });
    }
}
