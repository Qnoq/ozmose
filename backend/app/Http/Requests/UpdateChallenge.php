<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChallenge extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier si l'utilisateur est le créateur du défi
        $challenge = $this->route('challenge');
        return $challenge && $challenge->creator_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'instructions' => 'sometimes|string',
            'difficulty' => 'sometimes|string|in:facile,moyen,difficile',
            'duration' => 'nullable|integer|min:1',
            'category_id' => 'sometimes|exists:categories,id',
            'is_public' => 'sometimes|boolean',
        ];
    }
    
    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Le titre ne doit pas dépasser 255 caractères',
            'difficulty.in' => 'La difficulté doit être facile, moyen ou difficile',
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas',
            'duration.integer' => 'La durée doit être un nombre entier',
            'duration.min' => 'La durée doit être supérieure à 0',
        ];
    }
}