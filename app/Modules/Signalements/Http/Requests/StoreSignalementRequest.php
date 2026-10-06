<?php

namespace App\Modules\Signalements\Http\Requests;

use App\Modules\Signalements\Models\Signalement;
use App\Modules\Signalements\Services\SignalementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'motif.required' => 'Merci de préciser le motif du signalement.',
            'motif.min' => 'Le motif doit contenir au moins 10 caractères.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! app(SignalementService::class)->cibleExiste($this->input('cible_type'), $this->input('cible_id'))) {
                $validator->errors()->add('cible_id', "L'élément signalé est introuvable.");
            }
        });
    }
}
