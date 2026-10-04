<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Http\Requests\Concerns\ValidatesAtelierFields;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fiche de l'atelier connecté : ni user_id ni statut (réservés à l'admin).
 */
class UpdateMonAtelierRequest extends FormRequest
{
    use ValidatesAtelierFields;

    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_ATELIER;
    }

    public function rules(): array
    {
        return $this->atelierFieldRules();
    }

    public function messages(): array
    {
        return $this->atelierFieldMessages();
    }
}
