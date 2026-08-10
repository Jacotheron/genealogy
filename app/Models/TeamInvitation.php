<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;
use Override;

final class TeamInvitation extends JetstreamTeamInvitation
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
    ];

    public static function booted(): void
    {
        self::creating(static function (self $invitation) {
            $invitation->token = Str::random(40);
        });
    }

    /**
     * Get the team that the invitation belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    #[Override]
    public function team(): BelongsTo
    {
        /** @phpstan-ignore-next-line */
        return $this->belongsTo(Jetstream::teamModel());
    }
}
