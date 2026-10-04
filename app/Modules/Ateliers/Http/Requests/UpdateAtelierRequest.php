<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Http\Requests\Concerns\ValidatesAtelierFields;
use App\Modules\Ateliers\Models\Atelier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAtelierRequest extends FormRequest
{
    use ValidatesAtelierFields;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'user_id' => $this->userIdRules($this->atelierId()),
            ...$this->atelierFieldRules(),
            'statut' => ['required', Rule::in(Atelier::STATUTS)],
        ];
    }

    public function messages(): array
    {
        return $this->atelierFieldMessages();
    }

    /**
     * Id de l'atelier modifié, lu dans la route (pas de route model binding : paramètre string).
     */
    public function atelierId(): ?string
    {
        $id = $this->route('id') ?? $this->route('atelier');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
