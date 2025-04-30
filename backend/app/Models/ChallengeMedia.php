<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ChallengeMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'user_id',
        'participation_id',
        'type',
        'path',
        'media_type', // ancien champ
        'file_path',  // ancien champ
        'original_name',
        'size',
        'mime_type',
        'caption',
        'width',
        'height',
        'duration',
        'is_public',
        'order',
        'storage_disk'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'duration' => 'float',
        'order' => 'integer',
    ];
    
    /**
     * Mutateur pour assurer la compatibilité entre les champs type et media_type
     */
    public function setTypeAttribute($value)
    {
        $this->attributes['type'] = $value;
        $this->attributes['media_type'] = $value; // Synchroniser l'ancien champ
    }
    
    /**
     * Accesseur pour récupérer le type depuis l'un des deux champs
     */
    public function getTypeAttribute()
    {
        return $this->attributes['type'] ?? $this->attributes['media_type'] ?? null;
    }
    
    /**
     * Mutateur pour assurer la compatibilité entre les champs path et file_path
     */
    public function setPathAttribute($value)
    {
        $this->attributes['path'] = $value;
        $this->attributes['file_path'] = $value; // Synchroniser l'ancien champ
    }
    
    /**
     * Accesseur pour récupérer le chemin depuis l'un des deux champs
     */
    public function getPathAttribute()
    {
        return $this->attributes['path'] ?? $this->attributes['file_path'] ?? null;
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(ChallengeParticipation::class);
    }

    /**
     * Accesseur pour obtenir l'URL complète du média
     */
    public function getUrlAttribute()
    {
        $path = $this->getPathAttribute();
        if (!$path) return null;
        
        $disk = $this->storage_disk ?? 'public';
        return Storage::disk($disk)->url($path);
    }

    /**
     * Accesseur pour obtenir l'URL du thumbnail
     */
    public function getThumbnailUrlAttribute()
    {
        $path = $this->getPathAttribute();
        if (!$path) return null;
        
        $type = $this->getTypeAttribute();
        $disk = $this->storage_disk ?? 'public';
        
        if ($type === 'image' || $type === 'photo') {
            return Storage::disk($disk)->url('thumbnails/' . basename($path));
        } elseif ($type === 'video') {
            return Storage::disk($disk)->url('thumbnails/video_' . basename($path) . '.jpg');
        }
        
        return null;
    }
    
    /**
     * Méthode pour générer des URLs signées (temporaires)
     */
    public function getSignedUrl($expiration = 60) // minutes
    {
        return URL::temporarySignedRoute(
            'media.show',
            now()->addMinutes($expiration),
            ['media' => $this->id]
        );
    }
    
    /**
     * Accesseur pour obtenir des informations formatées sur le média
     */
    public function getInfoAttribute()
    {
        $info = [];
        
        if ($this->width && $this->height) {
            $info[] = "{$this->width}x{$this->height}";
        }
        
        if ($this->size) {
            $size = $this->size;
            if ($size < 1024 * 1024) {
                $info[] = round($size / 1024, 2) . " Ko";
            } else {
                $info[] = round($size / 1024 / 1024, 2) . " Mo";
            }
        }
        
        if ($this->duration) {
            $minutes = floor($this->duration / 60);
            $seconds = $this->duration % 60;
            $info[] = sprintf("%d:%02d", $minutes, $seconds);
        }
        
        return implode(' · ', $info);
    }
}