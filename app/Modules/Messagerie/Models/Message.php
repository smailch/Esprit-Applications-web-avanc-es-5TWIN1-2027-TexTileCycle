<?php

namespace App\Modules\Messagerie\Models;

use App\Modules\Auth\Models\User;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'cote',
        'contenu',
        'source',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return MessageFactory::new();
    }

    /** Conversation à laquelle appartient le message (N:1). */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', '_id');
    }

    /** Auteur du message (N:1 users). */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Représentation JSON utilisée par le polling (messagerie.js).
     */
    public function pourAffichage(User $lecteur, ?Conversation $conversation = null): array
    {
        $estSupport = ! ($conversation ?? $this->conversation)?->estAvecPartenaire();

        return [
            'id' => (string) $this->getKey(),
            'contenu' => $this->contenu,
            'moi' => (string) $this->user_id === (string) $lecteur->getKey(),
            'cote' => $this->cote,
            'auteur' => $this->cote === Conversation::COTE_ADMIN && $estSupport
                ? 'Équipe TexTileCycle'.($this->auteur ? ' · '.$this->auteur->name : '')
                : ($this->auteur?->name ?? 'Utilisateur'),
            'heure' => $this->created_at?->format('H:i'),
            'date' => $this->created_at?->locale('fr')->isoFormat('D MMMM YYYY'),
            'created_at' => $this->created_at?->format('Y-m-d\TH:i:s.vP'),
        ];
    }
}
