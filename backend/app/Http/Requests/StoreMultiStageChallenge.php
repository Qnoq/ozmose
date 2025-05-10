<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMultiStageChallenge extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier que l'utilisateur est premium
        return $this->user()->isPremium();
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
            'stages' => 'required|array|min:2|max:10', // Au moins 2 étapes, max 10
            'stages.*.title' => 'required|string|max:255',
            'stages.*.description' => 'required|string',
            'stages.*.instructions' => 'required|string',
            'stages.*.order' => 'required|integer|min:1',
            'stages.*.duration' => 'nullable|integer|min:1',
            'stages.*.requires_proof' => 'boolean',
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
            'stages.required' => 'Vous devez définir au moins 2 étapes',
            'stages.min' => 'Un défi multi-étapes doit contenir au moins 2 étapes',
            'stages.max' => 'Un défi multi-étapes ne peut pas contenir plus de 10 étapes',
            'stages.*.title.required' => 'Le titre de l\'étape est obligatoire',
            'stages.*.description.required' => 'La description de l\'étape est obligatoire',
            'stages.*.instructions.required' => 'Les instructions de l\'étape sont obligatoires',
            'stages.*.order.required' => 'L\'ordre de l\'étape est obligatoire',
            'stages.*.duration.min' => 'La durée d\'une étape doit être supérieure à 0',
        ];
    }
    
    /**
     * Get custom error messages for authorization failures.
     *
     * @return string
     */
    public function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException('Cette fonctionnalité est réservée aux utilisateurs premium.');
    }
}