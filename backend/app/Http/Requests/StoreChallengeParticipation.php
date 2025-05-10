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
        // Toujours autoriser la requête, la validation gèrera le cas de participation double
        return true;
    }
    
    public function rules(): array
    {
        $challenge = $this->route('challenge');
        
        return [
            'notes' => 'nullable|string',
            // Règle personnalisée pour vérifier la participation
            'user_participation' => [
                'required',
                function ($attribute, $value, $fail) use ($challenge) {
                    $existingParticipation = ChallengeParticipation::where('user_id', $this->user()->id)
                        ->where('challenge_id', $challenge->id)
                        ->exists();
                    
                    if ($existingParticipation) {
                        $fail('Vous participez déjà à ce défi');
                    }
                },
            ],
        ];
    }
    
    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'user_participation' => true, // Champ fictif pour la validation
        ]);
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