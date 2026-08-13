<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Userlog;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Userlog>
 */
final class UserlogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Always generate in UTC (no DST gaps ever)
        $createdAt = $this->randomUtcDateTime('-2 years', '-1 day');
        $updatedAt = $this->randomUtcDateTimeAfter($createdAt);

        return [
            'user_id'      => User::factory(),
            'country_name' => $this->faker->country(),
            'country_code' => $this->faker->countryCode(),
            'created_at'   => $createdAt,
            'updated_at'   => $updatedAt,
        ];
    }

    /**
     * @throws DateMalformedStringException
     */
    private function randomUtcDateTime(string $start, string $end): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $this->faker->dateTimeBetween($start, $end, 'UTC')->format('Y-m-d H:i:s'),
            new DateTimeZone('UTC')
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    private function randomUtcDateTimeAfter(DateTimeImmutable $after): DateTimeImmutable
    {
        // Convert to string so Faker can handle it
        $start = $after->format('Y-m-d H:i:s');

        return new DateTimeImmutable(
            $this->faker->dateTimeBetween($start, 'now', 'UTC')->format('Y-m-d H:i:s'),
            new DateTimeZone('UTC')
        );
    }
}
