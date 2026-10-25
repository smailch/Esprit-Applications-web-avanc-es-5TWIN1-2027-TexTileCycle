<?php

namespace App\Modules\Vetements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyserImageVetementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Choisissez une photo de vêtement à analyser.',
            'photo.image' => 'La photo doit être une image (JPG, PNG ou WebP).',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
        ];
    }
}
