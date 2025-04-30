<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ChallengeResource;
use App\Services\CacheService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    protected $cacheService;

    /**
     * Constructor avec injection du service de cache
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Afficher la liste des catégories.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        // Si l'utilisateur est connecté, on utilise le cache spécifique à l'utilisateur
        if (auth()->check()) {
            $categories = $this->cacheService->getCategoriesWithChallengeCount(auth()->id());
            return CategoryResource::collection($categories);
        }
        
        // Sinon, on récupère toutes les catégories sans comptage spécifique
        return $this->cacheService->getAllCategories();
    }

    /**
     * Afficher les détails d'une catégorie spécifique.
     *
     * @param Category $category
     * @return CategoryResource
     */
    public function show(Category $category)
    {
        // Ce cas particulier n'est pas mis en cache car il est moins fréquent
        // et plus spécifique à une catégorie donnée
        $category->loadCount([
            'challenges' => function($query) {
                $query->where(function($q) {
                    $q->where('is_public', true)
                        ->orWhere('creator_id', auth()->id());
                });
            }
        ]);
        
        return new CategoryResource($category);
    }

    /**
     * Récupère tous les défis d'une catégorie spécifique
     *
     * @param  Category  $category
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function challenges(Category $category, Request $request)
    {
        $page = $request->get('page', 1);
        $userId = auth()->check() ? auth()->id() : null;
        
        // Construire une clé de cache unique
        $cacheKey = "ozmose:categories:{$category->id}:challenges:page_{$page}";
        if ($userId) {
            $cacheKey .= ":user_{$userId}";
        }
        
        // Utiliser directement le Cache facade pour ce cas spécifique
        $challenges = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($category, $userId) {
            $query = $category->challenges()
                ->with(['creator', 'category'])
                ->where(function($query) use ($userId) {
                    $query->where('is_public', true);
                    if ($userId) {
                        $query->orWhere('creator_id', $userId);
                    }
                })
                ->orderBy('created_at', 'desc');
                
            return $query->paginate(10);
        });
        
        return ChallengeResource::collection($challenges);
    }
}