<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChallengeMediaResource;
use App\Models\ChallengeMedia;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Récupère les médias de l'utilisateur avec pagination
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = ChallengeMedia::where('user_id', $user->id);

        // Filtrer par type si spécifié
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filtrer par défi si spécifié
        if ($request->has('challenge_id')) {
            $query->where('challenge_id', $request->challenge_id);
        }

        // Filtrer par participation si spécifié
        if ($request->has('participation_id')) {
            $query->where('participation_id', $request->participation_id);
        }

        // Exclure les médias qui font partie d'une compilation si demandé
        if ($request->has('exclude_in_compilation') && $request->exclude_in_compilation) {
            $query->where('in_compilation', false);
        }

        // Filtrer par compilation si spécifié
        if ($request->has('is_compilation')) {
            $query->where('type', 'compilation');
        }

        // Trier par date de création par défaut
        $query->orderBy('created_at', 'desc');

        $medias = $query->paginate(15);

        return ChallengeMediaResource::collection($medias);
    }

    /**
     * Récupère un média spécifique
     */
    public function show(ChallengeMedia $media): JsonResponse
    {
        $user = auth()->user();

        // Vérifier si l'utilisateur a le droit d'accéder à ce média
        if ($media->user_id !== $user->id && !$media->is_public) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à voir ce média'
            ], 403);
        }

        return response()->json([
            'media' => new ChallengeMediaResource($media)
        ]);
    }

    /**
     * Supprime un média
     */
    public function destroy(ChallengeMedia $media): JsonResponse
    {
        $user = auth()->user();

        // Vérifier si l'utilisateur a le droit de supprimer ce média
        if ($media->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce média'
            ], 403);
        }

        try {
            // Si c'est une compilation, mettre à jour les médias associés
            if ($media->type === 'compilation') {
                ChallengeMedia::where('compilation_id', $media->id)
                    ->update([
                        'in_compilation' => false,
                        'compilation_id' => null
                    ]);
            }

            // Supprimer le média
            $this->mediaService->delete($media);

            return response()->json([
                'message' => 'Média supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du média', [
                'error' => $e->getMessage(),
                'media_id' => $media->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la suppression du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour les informations d'un média
     */
    public function update(Request $request, ChallengeMedia $media): JsonResponse
    {
        $user = auth()->user();

        // Vérifier si l'utilisateur a le droit de modifier ce média
        if ($media->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à modifier ce média'
            ], 403);
        }

        $validated = $request->validate([
            'caption' => 'sometimes|nullable|string|max:255',
            'is_public' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
        ]);

        $media->update($validated);

        return response()->json([
            'message' => 'Média mis à jour avec succès',
            'media' => new ChallengeMediaResource($media->fresh())
        ]);
    }

    /**
     * Crée une compilation à partir de plusieurs médias (réservé aux utilisateurs premium)
     */
    public function createCompilation(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est premium
        if (!$user->isPremium()) {
            return response()->json([
                'message' => 'La création de compilations est réservée aux utilisateurs premium',
                'premium_info' => [
                    'can_upgrade' => true,
                    'features' => ['Création de compilations', 'Qualité vidéo HD', 'Stockage augmenté']
                ]
            ], 403);
        }

        $validated = $request->validate([
            'media_ids' => 'required|array|min:2',
            'media_ids.*' => 'required|exists:challenge_media,id',
            'title' => 'required|string|max:255',
            'caption' => 'nullable|string|max:500',
            'is_public' => 'nullable|boolean',
        ]);

        try {
            // Créer la compilation
            $compilation = $this->mediaService->createCompilation(
                $validated['media_ids'],
                [
                    'title' => $validated['title'],
                    'caption' => $validated['caption'] ?? 'Compilation',
                    'is_public' => $validated['is_public'] ?? true
                ]
            );

            return response()->json([
                'message' => 'Compilation créée avec succès',
                'compilation' => new ChallengeMediaResource($compilation)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la compilation', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'media_ids' => $validated['media_ids']
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création de la compilation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les compilations de l'utilisateur
     */
    public function getUserCompilations(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $compilations = ChallengeMedia::where('user_id', $user->id)
            ->where('type', 'compilation')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return ChallengeMediaResource::collection($compilations);
    }

    /**
     * Récupère les détails d'une compilation
     */
    public function getCompilationDetails(ChallengeMedia $media): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que c'est bien une compilation
        if ($media->type !== 'compilation') {
            return response()->json([
                'message' => 'Ce média n\'est pas une compilation'
            ], 400);
        }

        // Vérifier si l'utilisateur a le droit d'accéder à cette compilation
        if ($media->user_id !== $user->id && !$media->is_public) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à voir cette compilation'
            ], 403);
        }

        try {
            // Récupérer les médias sources
            $sources = $this->mediaService->getCompilationSources($media);

            return response()->json([
                'compilation' => new ChallengeMediaResource($media),
                'sources' => ChallengeMediaResource::collection($sources)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des détails de la compilation', [
                'error' => $e->getMessage(),
                'media_id' => $media->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la récupération des détails',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une compilation
     */
    public function deleteCompilation(ChallengeMedia $media): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que c'est bien une compilation
        if ($media->type !== 'compilation') {
            return response()->json([
                'message' => 'Ce média n\'est pas une compilation'
            ], 400);
        }

        // Vérifier si l'utilisateur a le droit de supprimer cette compilation
        if ($media->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette compilation'
            ], 403);
        }

        try {
            // Mettre à jour les médias sources pour les retirer de la compilation
            ChallengeMedia::where('compilation_id', $media->id)
                ->update([
                    'in_compilation' => false,
                    'compilation_id' => null
                ]);

            // Supprimer la compilation
            $this->mediaService->delete($media);

            return response()->json([
                'message' => 'Compilation supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la compilation', [
                'error' => $e->getMessage(),
                'media_id' => $media->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la suppression de la compilation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 