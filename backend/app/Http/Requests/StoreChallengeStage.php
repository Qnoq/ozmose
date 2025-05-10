<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChallengeStage extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier que l'utilisateur est premium et créateur du défi
        $challenge = $this->route('challenge');
        return $this->user()->isPremium() && $challenge->creator_id === $this->user()->id;
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
            'instructions' => 'required|string',
            'order' => 'required|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'requires_proof' => 'boolean',
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
            'title.required' => 'Le titre de l\'étape est obligatoire',
            'description.required' => 'La description de l\'étape est obligatoire',
            'instructions.required' => 'Les instructions de l\'étape sont obligatoires',
            'order.required' => 'L\'ordre de l\'étape est obligatoire',
            'duration.min' => 'La durée de l\'étape doit être supérieure à 0',
        ];
    }
}