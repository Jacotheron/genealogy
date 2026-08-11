<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use RuntimeException;
use Throwable;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws Throwable
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'firstname' => ['nullable', 'string', 'max:255'],
            'surname'   => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'language'  => ['required', Rule::in(config('app.available_locales'))],
            'timezone'  => ['required', Rule::in(timezone_identifiers_list())],
            'password'  => $this->passwordRules(),
            'terms'     => ['accepted', 'required'],
            'token'     => ['required', 'string'],
        ])->validate();

        $invitationModel = Jetstream::teamInvitationModel();
        $invitation      = $invitationModel::where('email', $input['email'])
            ->where('token', $input['token'])
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'email' => ['This email does not have a valid, active team invitation.'],
            ]);
        }

        return DB::transaction(fn () => tap(User::create([
            'firstname' => $input['firstname'] ?? null,
            'surname'   => $input['surname'],
            'email'     => $input['email'],
            'language'  => $input['language'],
            'timezone'  => $input['timezone'],
            'password'  => $input['password'],
        ]), function (User $user): void {
            $this->createTeam($user);
        }));
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): void
    {
        return; // we do not need each user to have their own team
        /** @var Team $team */
        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Team ' . $user->name,
            'personal_team' => true,
        ]));

        if (! $team) {
            throw new RuntimeException('Failed to create team for user');
        }

        // Set the current_team_id to the newly created personal team
        $user->forceFill([
            'current_team_id' => $team->id,
        ])->save();
    }
}
