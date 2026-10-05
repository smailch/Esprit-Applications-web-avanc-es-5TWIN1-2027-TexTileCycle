<?php

namespace App\Modules\Vetements\Http\Requests;

use App\Modules\Vetements\Models\Vetement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntendedActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'intended_action' => ['required', Rule::in(Vetement::INTENDED_ACTIONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'intended_action.required' => 'Choisissez réparation ou don.',
            'intended_action.in' => 'Action invalide.',
        ];
    }
}
