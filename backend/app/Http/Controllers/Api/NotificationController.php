<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * Récupérer toutes les notifications de l'utilisateur
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->notifications()->paginate(20);
        return NotificationResource::collection($notifications);
    }
    
    /**
     * Récupérer les notifications non lues
     */
    public function unread(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->unreadNotifications()->paginate(20);
        return NotificationResource::collection($notifications);
    }
    
    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(string $id, Request $request)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        
        if (!$notification) {
            return response()->json([
                'message' => 'Notification non trouvée'
            ], 404);
        }
        
        $notification->markAsRead();
        
        return response()->json([
            'message' => 'Notification marquée comme lue'
        ]);
    }
    
    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        
        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues'
        ]);
    }
    
    /**
     * Supprimer une notification
     */
    public function destroy(string $id, Request $request)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        
        if (!$notification) {
            return response()->json([
                'message' => 'Notification non trouvée'
            ], 404);
        }
        
        $notification->delete();
        
        return response()->json([
            'message' => 'Notification supprimée'
        ]);
    }

    /**
     * Obtenir le nombre de notifications non lues
     */
    public function count(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();
        
        return response()->json([
            'count' => $count
        ]);
    }
}