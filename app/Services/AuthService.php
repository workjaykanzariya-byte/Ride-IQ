<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    public function findOrCreateUser(string $mobile): User
    {
        return User::query()->firstOrCreate(
            ['mobile' => $mobile],
            [
                'name' => null,
                'role' => 'rider',
            ]
        );
    }

    public function createToken(User $user): string
    {
        return $user->createToken('mobile-api-token')->plainTextToken;
    }
}
