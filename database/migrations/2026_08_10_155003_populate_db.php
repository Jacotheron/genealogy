<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settingsData = [
            ['key' => 'log_all_queries', 'value' => false],
            ['key' => 'log_all_queries_slow', 'value' => true],
            ['key' => 'log_all_queries_slow_threshold', 'value' => 500],
            ['key' => 'log_all_queries_nplusone', 'value' => true],
        ];

        Setting::insert($settingsData);

        $user = App\Models\User::query()->create([
            'firstname'    => 'Admin',
            'surname'      => 'Administrator',
            'email'        => 'admin@domain.test',
            'password'     => 'password',
            'is_developer' => true,
        ]);
        $team = App\Models\Team::query()->create([
            'name'          => 'Main Team',
            'personal_team' => false,
            'user_id'       => $user->id,
        ]);
        $team->users()->attach($team, ['role' => 'administrator']);
        $user->current_team_id   = $team->id;
        $user->email_verified_at = now();
        $user->save();
    }
};
