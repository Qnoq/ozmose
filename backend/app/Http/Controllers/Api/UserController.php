<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\ChallengeResource;
use App\Http\Resources\FriendshipResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Afficher le profil de l'utilisateur connecté
     */
    public function show(Request $request)
    {
        $user = $request->user()->load(['createdChallenges', 'participatingChallenges', 'friendsOfMine', 'friendOf']);
        info($user);
        return new UserResource($user);
    }
    
    /**
     * Mettre à jour le profil de l'utilisateur connecté
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'sometimes|string|min:8|confirmed',
            'avatar' => 'sometimes|image|max:2048', // Max 2MB
            'bio' => 'sometimes|string|nullable',
            'preferences' => 'sometimes|array',
        ]);
        
        // Traitement de l'avatar si présent
        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar s'il existe
            if ($user->avatar && Storage::exists($user->avatar)) {
                Storage::delete($user->avatar);
            }
            
            // Stocker le nouvel avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }
        
        // Hasher le mot de passe si présent
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        
        $user->update($validated);
        
        return new UserResource($user);
    }
    
    /**
     * Afficher les défis créés par l'utilisateur connecté
     */
    public function createdChallenges(Request $request)
    {
        $user = $request->user();
        
        $challenges = $user->createdChallenges()
            ->with(['category', 'media'])
            ->withCount('participations')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return ChallengeResource::collection($challenges);
    }
    
    /**
     * Afficher les défis auxquels l'utilisateur participe
     */
    public function participatingChallenges(Request $request)
    {
        $user = $request->user();
        
        $challenges = $user->participatingChallenges()
            ->with(['creator', 'category', 'media'])
            ->withPivot(['status', 'completed_at'])
            ->orderBy('challenge_participations.created_at', 'desc')
            ->paginate(15);
            
        return ChallengeResource::collection($challenges);
    }
    
    /**
     * Afficher la liste des amis de l'utilisateur
     */
    public function friends(Request $request)
    {
        $user = $request->user();
        
        $friends = $user->friends()
            ->paginate(20);
            
        return UserResource::collection($friends);
    }
    
    /**
     * Afficher les demandes d'amitié en attente
     */
    public function pendingFriendRequests(Request $request)
    {
        $user = $request->user();
        
        $requests = $user->pendingFriendRequests()
            ->paginate(20);
            
        return UserResource::collection($requests);
    }
    
    /**
     * Rechercher des utilisateurs (pour ajouter des amis)
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
        ]);
        
        $users = User::where('name', 'like', "%{$validated['query']}%")
            ->orWhere('email', 'like', "%{$validated['query']}%")
            ->where('id', '!=', $request->user()->id)
            ->paginate(20);
            
        return UserResource::collection($users);
    }
    
    /**
     * Afficher le profil d'un autre utilisateur
     */
    public function showOtherUser(User $user, Request $request)
    {
        $currentUser = $request->user();
        
        // Vérifier si l'utilisateur demandé est un ami
        $isFriend = $currentUser->friends()
            ->where('friend_id', $user->id)
            ->exists();
            
        // Charger plus d'informations si c'est un ami
        if ($isFriend) {
            $user->load(['createdChallenges' => function($query) {
                $query->where('is_public', true)->take(5);
            }]);
        }
        
        // Toujours inclure le statut d'amitié
        $friendshipStatus = null;
        
        if ($isFriend) {
            $friendshipStatus = 'friend';
        } else {
            // Vérifier s'il y a une demande d'amitié en attente
            $pendingRequest = $currentUser->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
                ->where('friend_id', $user->id)
                ->wherePivot('status', 'pending')
                ->first();
                
            if ($pendingRequest) {
                $friendshipStatus = 'pending_sent';
            } else {
                $pendingReceived = $currentUser->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
                    ->where('user_id', $user->id)
                    ->wherePivot('status', 'pending')
                    ->first();
                    
                if ($pendingReceived) {
                    $friendshipStatus = 'pending_received';
                } else {
                    $friendshipStatus = 'none';
                }
            }
        }
        
        return (new UserResource($user))
            ->additional(['friendship_status' => $friendshipStatus]);
    }
}