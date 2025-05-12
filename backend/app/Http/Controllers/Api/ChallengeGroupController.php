<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Models\ChallengeGroup;
use App\Services\LimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\ChallengeResource;
use App\Http\Resources\ChallengeGroupResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChallengeGroupController extends Controller
{
    protected $limitsService;

    public function __construct(LimitsService $limitsService)
    {
        $this->limitsService = $limitsService;
    }

    /**
     * Affiche la liste des groupes de l'utilisateur.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        
        // Récupérer les groupes où l'utilisateur est membre
        $groups = $user->challengeGroups()
            ->withCount('members', 'challenges')
            ->paginate(10);
        
        return ChallengeGroupResource::collection($groups);
    }

    /**
     * Crée un nouveau groupe de défis.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Pour les utilisateurs non premium, vérifier la limite de groupes
        if (!$user->isPremium()) {
            $groupCount = $user->challengeGroups()->count();
            $limit = 3; // Limite pour les utilisateurs gratuits
            
            if ($groupCount >= $limit) {
                return response()->json([
                    'message' => "Vous avez atteint la limite de {$limit} groupes pour les utilisateurs gratuits",
                    'premium_info' => [
                        'can_upgrade' => true,
                        'features' => ['Groupes illimités', 'Et bien plus encore...']
                    ]
                ], 403);
            }
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'nullable|integer|min:2|max:100',
        ]);
        
        // Définir la limite de membres en fonction du statut premium
        if (!isset($validated['max_members'])) {
            $validated['max_members'] = $user->isPremium() ? 50 : 10;
        } else {
            // Limiter le max_members pour les utilisateurs non premium
            if (!$user->isPremium() && $validated['max_members'] > 10) {
                $validated['max_members'] = 10;
            }
        }
        
        DB::beginTransaction();
        try {
            // Créer le groupe
            $group = ChallengeGroup::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'creator_id' => $user->id,
                'premium_only' => $request->has('premium_only') ? $request->premium_only : false,
                'max_members' => $validated['max_members'],
            ]);
            
            // Ajouter le créateur comme membre avec le rôle "creator"
            $group->members()->attach($user->id, ['role' => 'creator']);
            
            DB::commit();
            
            // Charger les relations pour la ressource
            $group->load('creator');
            $group->loadCount('members', 'challenges');
            
            return response()->json([
                'message' => 'Groupe créé avec succès',
                'group' => new ChallengeGroupResource($group)
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Erreur lors de la création du groupe', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Une erreur est survenue lors de la création du groupe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche les détails d'un groupe spécifique.
     *
     * @param ChallengeGroup $challengeGroup
     * @return JsonResponse
     */
    public function show(ChallengeGroup $challengeGroup): JsonResponse
    {
        // Vérifier que l'utilisateur est membre du groupe
        if (!$this->checkMembership(auth()->user(), $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas membre de ce groupe'
            ], 403);
        }
        
        // Charger les relations nécessaires
        $challengeGroup->load('creator', 'members', 'challenges');
        $challengeGroup->loadCount('members', 'challenges');
        
        return response()->json([
            'group' => new ChallengeGroupResource($challengeGroup)
        ]);
    }

    /**
     * Met à jour un groupe spécifique.
     *
     * @param Request $request
     * @param ChallengeGroup $challengeGroup
     * @return JsonResponse
     */
    public function update(Request $request, ChallengeGroup $challengeGroup): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est admin ou créateur du groupe
        if (!$this->checkAdminRole($user, $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'avez pas les droits pour modifier ce groupe'
            ], 403);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'nullable|integer|min:2',
            'premium_only' => 'nullable|boolean',
        ]);
        
        // Limiter max_members pour les non premium
        if (isset($validated['max_members']) && !$user->isPremium() && $validated['max_members'] > 10) {
            $validated['max_members'] = 10;
        }
        
        // Seul le créateur peut changer le statut premium_only
        if (isset($validated['premium_only']) && $challengeGroup->creator_id !== $user->id) {
            unset($validated['premium_only']);
        }
        
        $challengeGroup->update($validated);
        
        return response()->json([
            'message' => 'Groupe mis à jour avec succès',
            'group' => new ChallengeGroupResource($challengeGroup->fresh())
        ]);
    }

    /**
     * Supprime un groupe spécifique.
     *
     * @param ChallengeGroup $challengeGroup
     * @return JsonResponse
     */
    public function destroy(ChallengeGroup $challengeGroup): JsonResponse
    {
        $user = auth()->user();
        
        // Seul le créateur peut supprimer le groupe
        if ($challengeGroup->creator_id !== $user->id) {
            return response()->json([
                'message' => 'Seul le créateur du groupe peut le supprimer'
            ], 403);
        }
        
        DB::beginTransaction();
        try {
            // Détacher tous les membres
            $challengeGroup->members()->detach();
            
            // Détacher tous les défis (mais ne pas les supprimer)
            $challengeGroup->challenges()->detach();
            
            // Supprimer le groupe
            $challengeGroup->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Groupe supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Erreur lors de la suppression du groupe', [
                'error' => $e->getMessage(),
                'group_id' => $challengeGroup->id,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Une erreur est survenue lors de la suppression du groupe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajoute un membre au groupe.
     *
     * @param Request $request
     * @param ChallengeGroup $challengeGroup
     * @return JsonResponse
     */
    public function addMember(Request $request, ChallengeGroup $challengeGroup): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est admin ou créateur du groupe
        if (!$this->checkAdminRole($user, $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'avez pas les droits pour ajouter des membres à ce groupe'
            ], 403);
        }
        
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|in:member,admin',
        ]);
        
        $role = $validated['role'] ?? 'member';
        $memberUser = User::find($validated['user_id']);
        
        // Vérifier que l'utilisateur n'est pas déjà membre
        if ($challengeGroup->members()->where('user_id', $validated['user_id'])->exists()) {
            return response()->json([
                'message' => 'Cet utilisateur est déjà membre du groupe'
            ], 400);
        }
        
        // Vérifier la limite de membres
        if ($challengeGroup->members()->count() >= $challengeGroup->max_members) {
            return response()->json([
                'message' => 'Le groupe a atteint sa limite de membres',
                'premium_info' => !$user->isPremium() ? [
                    'can_upgrade' => true,
                    'features' => ['Augmentez la limite de membres dans vos groupes']
                ] : null
            ], 400);
        }
        
        // Vérifier si le groupe est premium_only et si le membre est premium
        if ($challengeGroup->premium_only && !$memberUser->isPremium()) {
            return response()->json([
                'message' => 'Ce groupe est réservé aux membres premium'
            ], 400);
        }
        
        // Ajouter le membre
        $challengeGroup->members()->attach($validated['user_id'], ['role' => $role]);
        
        return response()->json([
            'message' => 'Membre ajouté avec succès',
            'member' => new UserResource($memberUser)
        ]);
    }

    /**
     * Supprime un membre du groupe.
     *
     * @param Request $request
     * @param ChallengeGroup $challengeGroup
     * @param User $user
     * @return JsonResponse
     */
    public function removeMember(Request $request, ChallengeGroup $challengeGroup, User $user): JsonResponse
    {
        $currentUser = $request->user();
        
        // Cas 1: L'utilisateur se retire lui-même
        $selfRemoval = $currentUser->id === $user->id;
        
        // Cas 2: Admin/créateur retire quelqu'un d'autre
        $hasRights = $this->checkAdminRole($currentUser, $challengeGroup);
        
        // Cas 3: On ne peut pas retirer le créateur
        $isCreator = $user->id === $challengeGroup->creator_id;
        
        if (!$selfRemoval && !$hasRights) {
            return response()->json([
                'message' => 'Vous n\'avez pas les droits pour retirer des membres de ce groupe'
            ], 403);
        }
        
        if ($isCreator) {
            return response()->json([
                'message' => 'Le créateur ne peut pas être retiré du groupe'
            ], 400);
        }
        
        // Vérifier que l'utilisateur est bien membre
        if (!$challengeGroup->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas membre du groupe'
            ], 400);
        }
        
        // Retirer le membre
        $challengeGroup->members()->detach($user->id);
        
        return response()->json([
            'message' => $selfRemoval ? 'Vous avez quitté le groupe' : 'Membre retiré avec succès'
        ]);
    }

    /**
     * Change le rôle d'un membre du groupe.
     *
     * @param Request $request
     * @param ChallengeGroup $challengeGroup
     * @param User $user
     * @return JsonResponse
     */
    public function changeMemberRole(Request $request, ChallengeGroup $challengeGroup, User $user): JsonResponse
    {
        $currentUser = $request->user();
        
        // Seul le créateur peut changer les rôles
        if ($challengeGroup->creator_id !== $currentUser->id) {
            return response()->json([
                'message' => 'Seul le créateur du groupe peut changer les rôles'
            ], 403);
        }
        
        // On ne peut pas changer le rôle du créateur
        if ($user->id === $challengeGroup->creator_id) {
            return response()->json([
                'message' => 'Le rôle du créateur ne peut pas être modifié'
            ], 400);
        }
        
        $validated = $request->validate([
            'role' => 'required|string|in:member,admin',
        ]);
        
        // Vérifier que l'utilisateur est bien membre
        if (!$challengeGroup->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas membre du groupe'
            ], 400);
        }
        
        // Mettre à jour le rôle
        $challengeGroup->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);
        
        return response()->json([
            'message' => 'Rôle modifié avec succès',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $validated['role']
            ]
        ]);
    }

    /**
     * Ajoute un défi au groupe.
     *
     * @param Request $request
     * @param ChallengeGroup $challengeGroup
     * @return JsonResponse
     */
    public function addChallenge(Request $request, ChallengeGroup $challengeGroup): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est admin ou créateur du groupe
        if (!$this->checkAdminRole($user, $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'avez pas les droits pour ajouter des défis à ce groupe'
            ], 403);
        }
        
        $validated = $request->validate([
            'challenge_id' => 'required|exists:challenges,id',
        ]);
        
        $challenge = Challenge::find($validated['challenge_id']);
        
        // Vérifier que le défi n'est pas déjà dans le groupe
        if ($challengeGroup->challenges()->where('challenge_id', $validated['challenge_id'])->exists()) {
            return response()->json([
                'message' => 'Ce défi est déjà dans le groupe'
            ], 400);
        }
        
        // Vérifier que l'utilisateur est le créateur du défi ou que le défi est public
        if ($challenge->creator_id !== $user->id && !$challenge->is_public) {
            return response()->json([
                'message' => 'Vous ne pouvez pas ajouter ce défi au groupe'
            ], 403);
        }
        
        // Si le groupe est premium_only, vérifier que le défi n'est pas aussi premium_only
        // (évite la double restriction)
        if ($challengeGroup->premium_only && $challenge->premium_only) {
            // Mettre à jour le défi pour qu'il ne soit plus premium_only (il est déjà protégé par le groupe)
            $challenge->update(['premium_only' => false]);
        }
        
        // Ajouter le défi au groupe
        $challengeGroup->challenges()->attach($validated['challenge_id']);
        
        return response()->json([
            'message' => 'Défi ajouté au groupe avec succès',
            'challenge' => new ChallengeResource($challenge)
        ]);
    }

    /**
     * Supprime un défi du groupe.
     *
     * @param Request $request
     * @param ChallengeGroup $challengeGroup
     * @param Challenge $challenge
     * @return JsonResponse
     */
    public function removeChallenge(Request $request, ChallengeGroup $challengeGroup, Challenge $challenge): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est admin ou créateur du groupe
        if (!$this->checkAdminRole($user, $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'avez pas les droits pour retirer des défis de ce groupe'
            ], 403);
        }
        
        // Vérifier que le défi est bien dans le groupe
        if (!$challengeGroup->challenges()->where('challenge_id', $challenge->id)->exists()) {
            return response()->json([
                'message' => 'Ce défi n\'est pas dans le groupe'
            ], 400);
        }
        
        // Retirer le défi du groupe
        $challengeGroup->challenges()->detach($challenge->id);
        
        return response()->json([
            'message' => 'Défi retiré du groupe avec succès'
        ]);
    }

    /**
     * Récupère tous les défis d'un groupe.
     *
     * @param ChallengeGroup $challengeGroup
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function challenges(ChallengeGroup $challengeGroup)
    {
        $user = auth()->user();
        
        // Vérifier que l'utilisateur est membre du groupe
        if (!$this->checkMembership($user, $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas membre de ce groupe'
            ], 403);
        }
        
        // Récupérer les défis du groupe avec pagination
        $challenges = $challengeGroup->challenges()
            ->with(['creator', 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return ChallengeResource::collection($challenges);
    }

    /**
     * Récupère tous les membres d'un groupe.
     *
     * @param ChallengeGroup $challengeGroup
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function members(ChallengeGroup $challengeGroup)
    {
        $user = auth()->user();
        
        // Vérifier que l'utilisateur est membre du groupe
        if (!$this->checkMembership($user, $challengeGroup)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas membre de ce groupe'
            ], 403);
        }
        
        // Récupérer les membres du groupe avec leurs rôles
        $members = $challengeGroup->members()
            ->withPivot('role')
            ->orderByRaw("CASE WHEN role = 'creator' THEN 1 WHEN role = 'admin' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->paginate(20);
        
        return UserResource::collection($members);
    }

    /**
     * Vérifie si un utilisateur est membre d'un groupe.
     *
     * @param User $user
     * @param ChallengeGroup $group
     * @return bool
     */
    private function checkMembership(User $user, ChallengeGroup $group): bool
    {
        return $group->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Vérifie si un utilisateur a un rôle admin ou créateur dans un groupe.
     *
     * @param User $user
     * @param ChallengeGroup $group
     * @return bool
     */
    private function checkAdminRole(User $user, ChallengeGroup $group): bool
    {
        // Le créateur a toujours tous les droits
        if ($group->creator_id === $user->id) {
            return true;
        }
        
        // Vérifier si l'utilisateur est admin
        $membership = $group->members()
            ->where('user_id', $user->id)
            ->first();
        
        return $membership && $membership->pivot->role === 'admin';
    }
}