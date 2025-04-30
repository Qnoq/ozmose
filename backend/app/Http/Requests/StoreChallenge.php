<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChallenge extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Changez ceci en 'true' pour autoriser la requête
        // Vous pouvez ajouter une logique d'autorisation plus complexe si nécessaire
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty' => 'required|in:facile,moyen,difficile',
            'category_id' => 'required|exists:categories,id',
            'is_public' => 'boolean',
            'duration' => 'nullable|integer|min:1',
            'instructions' => 'required|string',
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
            'title.required' => 'Le titre du défi est obligatoire',
            'description.required' => 'La description du défi est obligatoire',
            'difficulty.in' => 'La difficulté doit être facile, moyenne ou difficile',
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas',
            'duration.integer' => 'La durée doit être un nombre entier',
            'duration.min' => 'La durée doit être supérieure à 0',
            'instructions.required' => 'Les instructions du défi sont obligatoires',
        ];
    }
}