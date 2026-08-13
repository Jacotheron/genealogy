<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

final class TeamInvitation extends JetstreamTeamInvitation
{
    public static function booted(): void
    {
        self::creating(static function (self $invitation) {
            $invitation->token = Str::random(40);
        });
    }
}
