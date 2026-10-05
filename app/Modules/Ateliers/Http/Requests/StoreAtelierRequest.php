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
        return [
            'user_id' => $this->userIdRules(),
            ...$this->atelierFieldRules(),
            'statut' => ['required', Rule::in(Atelier::STATUTS)],
        ];
    }

    public function messages(): array
    {
        return $this->atelierFieldMessages();
    }
}
