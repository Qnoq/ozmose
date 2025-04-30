<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;

class StoreChallengeParticipation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Autoriser l'utilisateur à participer au défi
        $challenge = $this->route('challenge');
        
        // Vérifier si l'utilisateur participe déjà à ce défi
        $existingParticipation = ChallengeParticipation::where('user_id', $this->user()->id)
            ->where('challenge_id', $challenge->id)
            ->exists();
            
        return !$existingParticipation;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // En général, pas besoin de valider des champs pour une participation initiale
            // puisque les données sont déduites du contexte (user_id, challenge_id)
            'notes' => 'nullable|string', // Si vous voulez permettre des notes initiales
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
            'notes.string' => 'Les notes doivent être au format texte',
        ];
    }
    
    /**
     * Get custom error messages for authorization failures.
     *
     * @return string
     */
    public function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException('Vous participez déjà à ce défi.');
    }
}