<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier que l'utilisateur est le propriétaire de la participation
        $participation = $this->route('participation');
        return $participation->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stage = $this->route('stage');
        
        // Si l'étape nécessite une preuve, la rendre obligatoire
        $mediaRule = $stage->requires_proof ? 'required' : 'nullable';
        
        return [
            'notes' => 'nullable|string',
            'proof_media' => $mediaRule . '|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480', // 20MB max
            'proof_caption' => 'nullable|string|max:255',
        ];
    }
    
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'proof_media.required' => 'Une preuve est requise pour compléter cette étape',
            'proof_media.mimes' => 'La preuve doit être une image ou une vidéo',
            'proof_media.max' => 'La taille maximale de la preuve est de 20 Mo',
        ];
    }
}