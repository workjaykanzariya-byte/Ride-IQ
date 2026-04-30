<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    public function profile(Request $request): JsonResponse
    {
        return $this->success('Profile fetched successfully', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $firstName = $validated['first_name'];
        $lastName = $validated['last_name'] ?? null;
        $name = trim($firstName.' '.($lastName ?? ''));

        $user->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'email' => $validated['email'] ?? null,
        ]);

        return $this->success('Profile updated successfully', [
            'user' => $user->fresh(),
        ]);
    }
}
