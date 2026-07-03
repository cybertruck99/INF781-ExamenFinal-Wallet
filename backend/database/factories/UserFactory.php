<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'ci' => fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('7#######'),
            'password' => static::$password ??= Hash::make('Password123*'),
            'role' => 'USER',
            'remember_token' => Str::random(10),
        ];
    }
}
