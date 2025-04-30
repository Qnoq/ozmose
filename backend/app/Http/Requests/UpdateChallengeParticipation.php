<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ChallengeParticipation;

class UpdateChallengeParticipation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier si l'utilisateur est bien le propriétaire de cette participation
        $participation = $this->route('participation');
        return $participation && $participation->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'sometimes|string|in:invited,accepted,completed,abandoned',
            'completed_at' => 'nullable|date',
            'abandoned_at' => 'nullable|date',
            'feedback' => 'nullable|string',
            'feedback_at' => 'nullable|date',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'proof_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480', // 20MB max
            'proof_caption' => 'nullable|string|max:255',
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
            'status.in' => 'Le statut doit être en cours, complété ou abandonné',
            'completed_at.date' => 'La date de complétion doit être une date valide',
            'rating.min' => 'La note doit être au minimum de 1',
            'rating.max' => 'La note doit être au maximum de 5',
            'feedback.string' => 'Le retour d\'expérience doit être au format texte',
            'notes.string' => 'Les notes doivent être au format texte',
        ];
    }
    
    /**
     * Get custom error messages for authorization failures.
     *
     * @return void
     */
    public function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException('Vous n\'êtes pas autorisé à modifier cette participation.');
    }
    
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Si le statut passe à "completed" et que completed_at n'est pas défini, le définir maintenant
        if ($this->status === 'completed' && !$this->has('completed_at')) {
            $this->merge([
                'completed_at' => now(),
            ]);
        }
    }
}