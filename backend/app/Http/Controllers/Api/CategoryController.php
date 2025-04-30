<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ChallengeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Afficher la liste des catégories.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        $categories = Category::withCount([
            'challenges' => function($query) {
                $query->where(function($q) {
                    $q->where('is_public', true)
                        ->orWhere('creator_id', auth()->id());
                });
            }
        ])->get();
        
        return CategoryResource::collection($categories);
    }

    /**
     * Afficher les détails d'une catégorie spécifique.
     *
     * @param Category $category
     * @return CategoryResource
     */
    public function show(Category $category)
    {
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
    public function challenges(Category $category)
    {
        $challenges = $category->challenges()
            ->with(['creator', 'category'])
            ->where(function($query) {
                $query->where('is_public', true)
                    ->orWhere('creator_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return ChallengeResource::collection($challenges);
    }
}
