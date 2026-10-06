<?php

namespace App\Modules\Signalements\Http\Requests\Back;

use App\Modules\Auth\Models\User;
use App\Modules\Signalements\Http\Requests\StoreSignalementRequest as BaseRequest;
use Illuminate\Validation\Validator;

/**
 * Saisie d'un signalement par l'administration (ex. reçu par téléphone) :
 * l'auteur est choisi dans la liste des utilisateurs.
 */
class StoreSignalementRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return parent::rules() + [
            'user_id' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'user_id.required' => 'Choisissez l\'auteur du signalement.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            if ($this->filled('user_id') && ! User::find($this->input('user_id'))) {
                $validator->errors()->add('user_id', 'Cet utilisateur n\'existe pas.');
            }
        });
    }
}
