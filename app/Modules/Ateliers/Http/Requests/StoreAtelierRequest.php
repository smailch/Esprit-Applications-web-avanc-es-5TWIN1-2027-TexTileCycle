<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Http\Requests\Concerns\ValidatesAtelierFields;
use App\Modules\Ateliers\Models\Atelier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAtelierRequest extends FormRequest
{
    use ValidatesAtelierFields;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        // array_merge plutôt que [...$tableau] : le dépaquetage à clés chaînes exige PHP 8.1 (projet : ^8.0).
        return array_merge(
            ['user_id' => $this->userIdRules()],
            $this->atelierFieldRules(),
            ['statut' => ['required', Rule::in(Atelier::STATUTS)]],
        );
    }

    public function messages(): array
    {
        return $this->atelierFieldMessages();
    }
}
