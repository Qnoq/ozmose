<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FriendshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'user_id' => 'sometimes|exists:users,id',
            'friend_id' => 'sometimes|exists:users,id|different:user_id',
            'status' => 'sometimes|in:pending,accepted,rejected',
        ];
    }
}