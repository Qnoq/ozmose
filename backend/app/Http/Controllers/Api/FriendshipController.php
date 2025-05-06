<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Notifications\FriendRequestNotification;
use App\Notifications\FriendRequestAcceptedNotification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FriendshipController extends Controller
{
    /**
     * Récupère la liste des amis de l'utilisateur connecté.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $friends = auth()->user()->friends();
        return UserResource::collection($friends);
    }

    /**
     * Envoie une demande d'amitié à un utilisateur.
     *
     * @param  User  $user
     * @return JsonResponse
     */
    public function sendRequest(User $user): JsonResponse
    {
        $currentUser = auth()->user();

        // Vérification que l'utilisateur ne s'envoie pas une demande à lui-même
        if ($currentUser->id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas vous envoyer une demande d\'amitié à vous-même'
            ], 400);
        }

        // Vérification si une relation d'amitié existe déjà
        $existingFriendship = Friendship::where(function($query) use ($currentUser, $user) {
            $query->where('user_id', $currentUser->id)
                  ->where('friend_id', $user->id);
        })->orWhere(function($query) use ($currentUser, $user) {
            $query->where('user_id', $user->id)
                  ->where('friend_id', $currentUser->id);
        })->first();

        if ($existingFriendship) {
            // Si une amitié acceptée existe déjà
            if ($existingFriendship->status === 'accepted') {
                return response()->json([
                    'message' => 'Vous êtes déjà amis avec cet utilisateur'
                ], 400);
            }
            
            // Si l'autre utilisateur a déjà envoyé une demande
            if ($existingFriendship->status === 'pending' && $existingFriendship->user_id === $user->id) {
                return response()->json([
                    'message' => 'Cet utilisateur vous a déjà envoyé une demande d\'amitié. Vous pouvez l\'accepter.'
                ], 400);
            }
            
            // Si l'utilisateur actuel a déjà envoyé une demande
            if ($existingFriendship->status === 'pending' && $existingFriendship->user_id === $currentUser->id) {
                return response()->json([
                    'message' => 'Vous avez déjà envoyé une demande d\'amitié à cet utilisateur'
                ], 400);
            }
            
            // Si la demande a été rejetée, on la réactive
            if ($existingFriendship->status === 'rejected') {
                $existingFriendship->status = 'pending';
                $existingFriendship->user_id = $currentUser->id;
                $existingFriendship->friend_id = $user->id;
                $existingFriendship->save();
                
                return response()->json([
                    'message' => 'Demande d\'amitié envoyée avec succès',
                    'friendship' => $existingFriendship
                ], 200);
            }
        }

        // Création d'une nouvelle demande d'amitié
        $friendship = Friendship::create([
            'user_id' => $currentUser->id,
            'friend_id' => $user->id,
            'status' => 'pending'
        ]);
        
        // Envoyer une notification
        $user->notify(new FriendRequestNotification(auth()->user()));

        return response()->json([
            'message' => 'Demande d\'amitié envoyée avec succès',
            'friendship' => $friendship
        ], 201);
    }

    /**
     * Accepte une demande d'amitié.
     *
     * @param  Friendship  $friendship
     * @return JsonResponse
     */
    public function acceptRequest(Friendship $friendship): JsonResponse
    {
        $currentUser = auth()->user();

        // Vérification que l'utilisateur est bien le destinataire de la demande
        if ($friendship->friend_id !== $currentUser->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à accepter cette demande d\'amitié'
            ], 403);
        }

        // Vérification que la demande est en attente
        if ($friendship->status !== 'pending') {
            return response()->json([
                'message' => 'Cette demande d\'amitié ne peut pas être acceptée'
            ], 400);
        }

        // Acceptation de la demande
        $friendship->status = 'accepted';
        $friendship->save();

        // Envoyer une notification
        $friendship->user->notify(new FriendRequestAcceptedNotification(auth()->user()));

        return response()->json([
            'message' => 'Demande d\'amitié acceptée avec succès',
            'friendship' => $friendship
        ], 200);
    }

    /**
     * Rejette une demande d'amitié.
     *
     * @param  Friendship  $friendship
     * @return JsonResponse
     */
    public function rejectRequest(Friendship $friendship): JsonResponse
    {
        $currentUser = auth()->user();

        // Vérification que l'utilisateur est bien le destinataire de la demande
        if ($friendship->friend_id !== $currentUser->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à rejeter cette demande d\'amitié'
            ], 403);
        }

        // Vérification que la demande est en attente
        if ($friendship->status !== 'pending') {
            return response()->json([
                'message' => 'Cette demande d\'amitié ne peut pas être rejetée'
            ], 400);
        }

        // Rejet de la demande
        $friendship->status = 'rejected';
        $friendship->save();

        return response()->json([
            'message' => 'Demande d\'amitié rejetée avec succès'
        ], 200);
    }

    /**
     * Supprime une relation d'amitié.
     *
     * @param  Friendship  $friendship
     * @return JsonResponse
     */
    public function destroy(Friendship $friendship): JsonResponse
    {
        $currentUser = auth()->user();

        // Vérification que l'utilisateur est concerné par cette amitié
        if ($friendship->user_id !== $currentUser->id && $friendship->friend_id !== $currentUser->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette amitié'
            ], 403);
        }

        // Vérification que l'amitié est acceptée
        if ($friendship->status !== 'accepted') {
            return response()->json([
                'message' => 'Cette relation n\'est pas une amitié acceptée'
            ], 400);
        }

        // Suppression de l'amitié
        $friendship->delete();

        return response()->json([
            'message' => 'Amitié supprimée avec succès'
        ], 200);
    }

    /**
     * Récupère les demandes d'amitié en attente de l'utilisateur connecté.
     *
     * @return AnonymousResourceCollection
     */
    public function pendingRequests(): AnonymousResourceCollection
    {
        // Récupérer les demandes d'amitié en attente
        $pendingRequests = auth()->user()->pendingFriendRequests;
        
        // Retourner les utilisateurs qui ont envoyé ces demandes
        return UserResource::collection($pendingRequests);
    }
}