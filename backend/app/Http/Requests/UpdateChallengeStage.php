<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChallengeStage extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Vérifier que l'utilisateur est premium et créateur du défi
        $stage = $this->route('stage');
        $challenge = $stage->challenge;
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'instructions' => 'sometimes|required|string',
            'order' => 'sometimes|required|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'requires_proof' => 'sometimes|boolean',
        ];
    }
}