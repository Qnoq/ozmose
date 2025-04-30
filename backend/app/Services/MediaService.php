<?php

namespace App\Services;

use App\Models\ChallengeMedia;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use App\Models\ChallengeParticipation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;

class MediaService
{
    protected $imageManager;
    
    public function __construct()
    {
        // Version 3 d'Intervention Image utilise un système de drivers différent
        $this->imageManager = new ImageManager(new GdDriver());
    }
    
    protected $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    protected $allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo'];
    
    // Limites pour utilisateurs gratuits et premium
    protected $limits = [
        'free' => [
            'max_file_size' => 10 * 1024 * 1024, // 10 MB
            'max_video_duration' => 60, // 60 secondes
            'max_image_dimension' => 1920, // 1920px max
            'image_quality' => 70, // Compression à 70%
            'video_quality' => 'medium', // Qualité moyenne
        ],
        'premium' => [
            'max_file_size' => 100 * 1024 * 1024, // 100 MB
            'max_video_duration' => 300, // 5 minutes
            'max_image_dimension' => 4000, // 4000px max
            'image_quality' => 100, // Qualité maximale
            'video_quality' => 'high', // Haute qualité
        ]
    ];
    
    /**
     * Traite et enregistre un média uploadé
     */
    public function store(UploadedFile $file, array $data = [])
    {
        // Récupérer l'utilisateur
        $user = auth()->user();
        
        // Validation du type de fichier et des limites
        $this->validateFile($file, $user);
        
        // Déterminer le type de média
        $type = $this->determineMediaType($file);
        
        // Générer un nom unique
        $filename = $this->generateUniqueFilename($file);
        
        // Déterminer le chemin de stockage
        $path = $this->getStoragePath($type, $data);
        
        // Traitement spécifique selon le type
        if (in_array($type, ['image', 'photo'])) {
            return $this->processImage($file, $filename, $path, $data, $user);
        } elseif ($type === 'video') {
            return $this->processVideo($file, $filename, $path, $data, $user);
        }
        
        return null;
    }
    
    /**
     * Traite une image (redimensionnement, compression, thumbnails)
     */
    protected function processImage(UploadedFile $file, $filename, $path, array $data, $user = null)
    {
        // Force l'utilisation du disque public
        $disk = 'public';
        
        // Si l'utilisateur n'est pas défini, utiliser l'utilisateur authentifié
        if (!$user) {
            $user = auth()->user();
        }
        
        // Charger l'image avec Intervention Image
        $image = $this->imageManager->read($file);
        
        // Récupérer les dimensions originales
        $originalWidth = $image->width();
        $originalHeight = $image->height();
        
        // Définir les limites selon le statut premium
        $isPremium = $user && method_exists($user, 'isPremium') ? $user->isPremium() : false;
        $userType = $isPremium ? 'premium' : 'free';
        $maxDimension = $this->limits[$userType]['max_image_dimension'];
        
        // Redimensionner si nécessaire (selon les limites de l'utilisateur)
        if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
            $image = $image->scaleDown(width: $maxDimension, height: $maxDimension);
        }
        
        // Obtenir l'extension du fichier pour l'encodage
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            // Par défaut, si extension non reconnue
            $extension = 'jpg';
        }
        
        // Optimiser et enregistrer l'image principale
        $fullPath = $path . '/' . $filename;
        
        // Dans le traitement des images pour les utilisateurs premium
        if ($isPremium) {
            // Encoder avec une compression minimale pour conserver la qualité
            $quality = $this->limits['premium']['image_quality'];
            $highQuality = true;
        } else {
            // Compression standard pour économiser de l'espace
            $quality = $this->limits['free']['image_quality'];
            $highQuality = false;
        }
        
        // Encoder l'image selon la qualité déterminée et l'extension
        // CORRECTION: Ne plus passer le niveau de compression pour PNG
        $encodedImage = match($extension) {
            'jpg', 'jpeg' => $image->toJpeg($quality),
            'png' => $image->toPng(), // Sans paramètre de compression
            'gif' => $image->toGif(),
            'webp' => $image->toWebp($quality),
            default => $image->toJpeg($quality),
        };
        
        Storage::disk($disk)->put($fullPath, $encodedImage);
        
        // Créer une miniature
        $thumbnail = $image->cover(width: 300, height: 300);
        
        // Encoder le thumbnail (toujours avec une qualité réduite pour les thumbnails)
        // CORRECTION: Ne plus passer le niveau de compression pour PNG
        $encodedThumbnail = match($extension) {
            'jpg', 'jpeg' => $thumbnail->toJpeg(70),
            'png' => $thumbnail->toPng(), // Sans paramètre de compression
            'gif' => $thumbnail->toGif(),
            'webp' => $thumbnail->toWebp(70),
            default => $thumbnail->toJpeg(70),
        };
        
        Storage::disk($disk)->put('thumbnails/' . $filename, $encodedThumbnail);
        
        // Log pour debug
        Log::info('Image process', [
            'user_id' => $user->id,
            'is_premium' => $isPremium,
            'quality' => $quality,
            'high_quality' => $highQuality,
            'original_size' => $file->getSize(),
            'max_dimension' => $maxDimension,
        ]);
        
        // Créer l'enregistrement en base de données
        return ChallengeMedia::create([
            'challenge_id' => $data['challenge_id'] ?? null,
            'user_id' => $data['user_id'] ?? $user->id,
            'participation_id' => $data['participation_id'] ?? null,
            'type' => 'image', // Sera synchronisé avec media_type dans le modèle
            'path' => $fullPath, // Sera synchronisé avec file_path dans le modèle
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'caption' => $data['caption'] ?? null,
            'width' => $originalWidth,
            'height' => $originalHeight,
            'is_public' => $data['is_public'] ?? true,
            'order' => $data['order'] ?? 0,
            'storage_disk' => $disk,
            'high_quality' => $highQuality,
            'in_compilation' => false,
            'compilation_id' => null
        ]);
    }
    
    /**
     * Traite une vidéo (compression, génération de thumbnail)
     */
    protected function processVideo(UploadedFile $file, $filename, $path, array $data, $user = null)
    {
        $disk = 'public';

        if (!$user) {
            $user = auth()->user();
        }

        $isPremium = $user && method_exists($user, 'isPremium') ? $user->isPremium() : false;
        $userType = $isPremium ? 'premium' : 'free';

        // 1. Stocker la vidéo brute
        $fullPath = $path . '/' . $filename;
        Storage::disk($disk)->putFileAs(
            dirname($fullPath),
            $file,
            basename($fullPath)
        );

        // 2. Récupérer le chemin absolu du fichier vidéo stocké
        $absolutePath = Storage::disk($disk)->path($fullPath);

        // 3. Utiliser FFmpeg pour extraire les infos et générer le thumbnail
        $duration = null;
        $width = null;
        $height = null;

        try {
            // FFProbe pour les infos
            $ffprobe = FFProbe::create();
            $duration = $ffprobe->format($absolutePath)->get('duration');
            $videoStream = $ffprobe->streams($absolutePath)->videos()->first();
            if ($videoStream) {
                $width = $videoStream->get('width');
                $height = $videoStream->get('height');
            }

            // FFMpeg pour le thumbnail
            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($absolutePath);

            // On génère le thumbnail à la 2e seconde (ou à 1s si la vidéo est très courte)
            $thumbnailTime = ($duration && $duration > 2) ? 2 : 1;
            $thumbnailFilename = 'thumbnails/video_' . basename($fullPath) . '.jpg';
            $thumbnailFullPath = Storage::disk($disk)->path($thumbnailFilename);

            $video->frame(TimeCode::fromSeconds($thumbnailTime))
                  ->save($thumbnailFullPath);

        } catch (\Exception $e) {
            // En cas d'erreur, on log mais on ne bloque pas l'upload
            Log::error('Erreur FFmpeg : ' . $e->getMessage());
        }

        // Log pour debug
        Log::info('Video process', [
            'user_id' => $user->id,
            'is_premium' => $isPremium,
            'quality' => $this->limits[$userType]['video_quality'],
            'max_duration' => $this->limits[$userType]['max_video_duration'],
            'original_size' => $file->getSize(),
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
        ]);

        // Créer l'enregistrement en base de données
        return ChallengeMedia::create([
            'challenge_id' => $data['challenge_id'] ?? null,
            'user_id' => $data['user_id'] ?? $user->id,
            'participation_id' => $data['participation_id'] ?? null,
            'type' => 'video',
            'path' => $fullPath,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'caption' => $data['caption'] ?? null,
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
            'is_public' => $data['is_public'] ?? true,
            'order' => $data['order'] ?? 0,
            'storage_disk' => $disk,
            'high_quality' => $isPremium,
            'in_compilation' => false,
            'compilation_id' => null
        ]);
    }
    
    /**
     * Valide le fichier uploadé et vérifie les limites selon le statut premium
     */
    protected function validateFile(UploadedFile $file, $user = null)
    {
        $mimeType = $file->getMimeType();
        
        if (!in_array($mimeType, array_merge($this->allowedImageTypes, $this->allowedVideoTypes))) {
            throw new \Exception('Type de fichier non supporté');
        }
        
        // Si l'utilisateur n'est pas défini, utiliser l'utilisateur authentifié
        if (!$user) {
            $user = auth()->user();
        }
        
        // Définir les limites selon le statut premium
        $isPremium = $user && method_exists($user, 'isPremium') ? $user->isPremium() : false;
        $userType = $isPremium ? 'premium' : 'free';
        
        // Vérifier la taille maximale selon le statut
        $maxSize = $this->limits[$userType]['max_file_size'];
        if ($file->getSize() > $maxSize) {
            throw new \Exception(
                'Le fichier est trop volumineux. ' . 
                'Taille maximum : ' . ($maxSize / 1024 / 1024) . ' Mo' .
                (!$isPremium ? ' (Les utilisateurs premium peuvent uploader des fichiers jusqu\'à ' . 
                ($this->limits['premium']['max_file_size'] / 1024 / 1024) . ' Mo)' : '')
            );
        }
        
        return true;
    }
    
    /**
     * Détermine le type de média à partir du fichier
     */
    protected function determineMediaType(UploadedFile $file)
    {
        $mimeType = $file->getMimeType();
        
        if (in_array($mimeType, $this->allowedImageTypes)) {
            return 'image';
        } elseif (in_array($mimeType, $this->allowedVideoTypes)) {
            return 'video';
        }
        
        throw new \Exception('Type de média non supporté');
    }
    
    /**
     * Génère un nom de fichier unique
     */
    protected function generateUniqueFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        return md5(uniqid() . time()) . '.' . $extension;
    }
    
    /**
     * Détermine le chemin de stockage du fichier
     */
    protected function getStoragePath($type, array $data)
    {
        // Organisation par type et relation
        if (isset($data['participation_id'])) {
            return "participations/{$data['participation_id']}/{$type}s";
        } elseif (isset($data['challenge_id'])) {
            return "challenges/{$data['challenge_id']}/{$type}s";
        } else {
            $userId = $data['user_id'] ?? auth()->id();
            return "users/{$userId}/{$type}s";
        }
    }
    
    /**
     * Supprime un média et ses fichiers associés
     */
    public function delete(ChallengeMedia $media)
    {
        $disk = $media->storage_disk ?? 'public';
        $path = $media->getPathAttribute();
        
        if ($path) {
            // Supprimer le fichier principal
            Storage::disk($disk)->delete($path);
            
            // Supprimer le thumbnail si c'est une image ou vidéo
            $type = $media->getTypeAttribute();
            if ($type === 'image' || $type === 'photo') {
                Storage::disk($disk)->delete('thumbnails/' . basename($path));
            } elseif ($type === 'video') {
                Storage::disk($disk)->delete('thumbnails/video_' . basename($path) . '.jpg');
            }
        }
        
        // Nettoyage des enfants si c'est une compilation
        if ($media->getTypeAttribute() === 'compilation') {
            ChallengeMedia::where('compilation_id', $media->id)
                ->where('in_compilation', true)
                ->update([
                    'in_compilation' => false,
                    'compilation_id' => null
                ]);
        }
        
        // Supprimer l'enregistrement
        return $media->delete();
    }

    /**
     * Traite et enregistre une preuve de réalisation
     */
    public function storeCompletionProof(UploadedFile $file, ChallengeParticipation $participation, array $data = [])
    {
        // Utiliser la méthode générique store avec les paramètres spécifiques
        $media = $this->store($file, [
            'challenge_id' => $participation->challenge_id,
            'participation_id' => $participation->id,
            'user_id' => $data['user_id'] ?? auth()->id(),
            'caption' => $data['caption'] ?? 'Preuve de réalisation',
            'is_public' => $data['is_public'] ?? false
        ]);
        
        // Mettre à jour automatiquement la participation
        $skipUpdate = $data['skip_participation_update'] ?? false;
        
        if ($media && !$skipUpdate) {
            $participation->update([
                'proof_media_id' => $media->id,
                'status' => 'completed',
                'completed_at' => now()
            ]);
        }
        
        return $media;
    }
    
    /**
     * Crée une compilation à partir de plusieurs médias pour un utilisateur premium
     */
    public function createCompilation(array $mediaIds, array $data = [])
    {
        $user = auth()->user();
        
        // Vérifier que l'utilisateur est premium
        if (!$user || !method_exists($user, 'isPremium') || !$user->isPremium()) {
            throw new \Exception('La création de compilations est réservée aux utilisateurs premium');
        }
        
        // Vérifier qu'il y a au moins 2 médias
        if (count($mediaIds) < 2) {
            throw new \Exception('Une compilation doit contenir au moins 2 médias');
        }
        
        // Vérifier que tous les médias appartiennent à l'utilisateur
        $medias = ChallengeMedia::whereIn('id', $mediaIds)
            ->where('user_id', $user->id)
            ->get();
        
        if ($medias->count() !== count($mediaIds)) {
            throw new \Exception('Certains médias sélectionnés n\'existent pas ou ne vous appartiennent pas');
        }
        
        // À ce stade, il faudrait utiliser FFmpeg pour créer la compilation
        // Pour l'instant, on va juste créer un enregistrement "compilation" qui référence ces médias
        
        // Créer l'enregistrement de compilation
        $compilation = ChallengeMedia::create([
            'user_id' => $user->id,
            'type' => 'compilation',
            'path' => null, // À remplacer par le chemin réel de la compilation
            'original_name' => $data['title'] ?? 'Compilation',
            'size' => 0, // À calculer une fois la compilation créée
            'mime_type' => 'video/mp4', // Par défaut pour une compilation
            'caption' => $data['caption'] ?? 'Compilation de médias',
            'width' => null, // À définir après création
            'height' => null, // À définir après création
            'duration' => null, // À calculer
            'is_public' => $data['is_public'] ?? true,
            'order' => 0,
            'storage_disk' => 'public',
            'high_quality' => true, // Les compilations sont toujours en haute qualité
            'in_compilation' => false,
            'compilation_id' => null
        ]);
        
        // Marquer les médias sources comme faisant partie de cette compilation
        foreach ($medias as $media) {
            $media->update([
                'in_compilation' => true,
                'compilation_id' => $compilation->id
            ]);
        }
        
        // Idéalement, lancer un job pour créer la compilation vidéo avec FFmpeg
        // Pour l'instant, on retourne juste l'enregistrement
        
        return $compilation;
    }
    
    /**
     * Retourne les sources d'une compilation
     */
    public function getCompilationSources(ChallengeMedia $compilation)
    {
        // Vérifier que c'est bien une compilation
        if ($compilation->type !== 'compilation') {
            throw new \Exception('Ce média n\'est pas une compilation');
        }
        
        // Retourner tous les médias qui font partie de cette compilation
        return ChallengeMedia::where('compilation_id', $compilation->id)
            ->where('in_compilation', true)
            ->orderBy('order')
            ->get();
    }
    
    /**
     * Vérifie les limites de stockage de l'utilisateur
     */
    public function checkStorageLimit($user = null)
    {
        if (!$user) {
            $user = auth()->user();
        }
        
        // Définir les limites selon le statut premium
        $isPremium = $user && method_exists($user, 'isPremium') ? $user->isPremium() : false;
        $storageLimit = $isPremium ? 20 * 1024 * 1024 * 1024 : 2 * 1024 * 1024 * 1024; // 20 GB vs 2 GB
        
        // Calculer l'espace utilisé
        $usedSpace = ChallengeMedia::where('user_id', $user->id)->sum('size');
        
        return [
            'used' => $usedSpace,
            'limit' => $storageLimit,
            'available' => $storageLimit - $usedSpace,
            'percentage' => round(($usedSpace / $storageLimit) * 100, 2),
            'is_premium' => $isPremium
        ];
    }
}