<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+1'.fake()->numerify('##########'),
            'firebase_uid' => fake()->unique()->regexify('[A-Za-z0-9_-]{28}'),
        ];
    }
}
