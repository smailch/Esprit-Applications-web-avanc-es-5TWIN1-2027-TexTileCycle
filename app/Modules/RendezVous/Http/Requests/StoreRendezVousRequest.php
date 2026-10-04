<?php

namespace App\Modules\RendezVous\Http\Requests;

use App\Modules\RendezVous\Services\RendezVousService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRendezVousRequest extends FormRequest
{
    /**
     * @var array{erreurs: array<string, string>, atelier: ?\App\Modules\Ateliers\Models\Atelier, service: ?\App\Modules\Ateliers\Models\Service, vetement: ?\App\Modules\Vetements\Models\Vetement}|null
     */
    private ?array $demande = null;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isCitoyen();
    }

    public function rules(): array
    {
        $objectId = 'regex:'.RendezVousService::OBJECT_ID;

        return [
            'atelier' => ['required', 'string', $objectId],
            'service' => ['required', 'string', $objectId],
            'vetement' => ['nullable', 'string', $objectId],
            'date' => ['required', 'date_format:Y-m-d'],
            'heure' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'atelier.required' => "Choisissez d'abord un atelier.",
            'atelier.string' => "Cet atelier n'est pas disponible à la réservation.",
            'atelier.regex' => "Cet atelier n'est pas disponible à la réservation.",
            'service.required' => 'Choisissez un service.',
            'service.string' => "Ce service n'est pas proposé par cet atelier.",
            'service.regex' => "Ce service n'est pas proposé par cet atelier.",
            'vetement.string' => 'Choisissez un de vos vêtements déclarés pour une réparation.',
            'vetement.regex' => 'Choisissez un de vos vêtements déclarés pour une réparation.',
            'date.required' => 'Choisissez une date.',
            'date.date_format' => 'La date doit être au format jj/mm/aaaa.',
            'heure.required' => 'Choisissez une heure.',
            'heure.date_format' => "L'heure doit être au format hh:mm.",
            'notes.string' => 'Le commentaire est invalide.',
            'notes.max' => 'Le commentaire ne peut pas dépasser 1000 caractères.',
        ];
    }

    /**
     * Les contrôles qui lisent la base (atelier actif, service, horaires, chevauchement)
     * ne tournent que si la forme est déjà valide.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->demande = app(RendezVousService::class)->verifierDemande(
                (string) $this->user()->getKey(),
                $validator->getData()
            );

            foreach ($this->demande['erreurs'] as $champ => $message) {
                $validator->errors()->add($champ, $message);
            }
        });
    }

    /**
     * Atelier, service et vêtement relus en base pendant la validation.
     *
     * @return array{atelier: \App\Modules\Ateliers\Models\Atelier, service: \App\Modules\Ateliers\Models\Service, vetement: ?\App\Modules\Vetements\Models\Vetement}
     */
    public function demande(): array
    {
        return [
            'atelier' => $this->demande['atelier'],
            'service' => $this->demande['service'],
            'vetement' => $this->demande['vetement'],
        ];
    }
}
