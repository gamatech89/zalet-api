<?php

declare(strict_types=1);

namespace App\Domains\Duel\Enums;

enum DuelEventType: string
{
    case GIFT_SENT = 'gift_sent';
    case USER_JOINED = 'user_joined';
    case USER_LEFT = 'user_left';
    case SCORE_UPDATED = 'score_updated';
    case ROUND_ENDED = 'round_ended';
    case DUEL_ENDED = 'duel_ended';
    case VIEWER_COMMENT = 'viewer_comment';
    case PAUSE = 'pause';
    case RESUME = 'resume';
    case HOST_READY = 'host_ready';
    case GUEST_READY = 'guest_ready';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::GIFT_SENT => 'Poklon poslat',
            self::USER_JOINED => 'Korisnik ušao',
            self::USER_LEFT => 'Korisnik izašao',
            self::SCORE_UPDATED => 'Rezultat ažuriran',
            self::ROUND_ENDED => 'Runda završena',
            self::DUEL_ENDED => 'Dvoboj završen',
            self::VIEWER_COMMENT => 'Komentar gledaoca',
            self::PAUSE => 'Pauza',
            self::RESUME => 'Nastavak',
            self::HOST_READY => 'Domaćin spreman',
            self::GUEST_READY => 'Gost spreman',
        };
    }
}
