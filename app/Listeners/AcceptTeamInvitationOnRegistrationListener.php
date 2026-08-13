<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Laravel\Jetstream\Jetstream;

class AcceptTeamInvitationOnRegistrationListener
{
    public function __construct() {}

    public function handle(Registered $event): void
    {
        $user = $event->user;
        /* @var User $user */

        // Grab the token used during this request session/payload
        $token = request()->input('token');

        if ($token) {
            $invitationModel = Jetstream::teamInvitationModel();
            $invitation      = $invitationModel::query()
                ->where('token', $token)
                ->where('email', $user->email)
                ->first();

            if ($invitation) {
                // Add user to the team natively using Jetstream's logic
                $invitation->team->users()->attach(
                    $user, ['role' => $invitation->role]
                );

                // Delete the invitation so it cannot be reused
                $invitation->delete();

                // Set the newly joined team as the current team for the user
                $user->forceFill([
                    'current_team_id' => $invitation->team_id,
                ])->save();
            }
        }
    }
}
