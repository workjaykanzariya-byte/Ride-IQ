<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TruvAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('truvAccounts');

        return $this->success('Profile fetched successfully', [
            'user' => $user,
            'truv_accounts' => TruvAccountResource::collection($user->truvAccounts),
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

        $name = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));

        $user->update([
            'name' => $name,
            'email' => $validated['email'] ?? null,
        ]);

        return $this->success('Profile updated successfully', [
            'user' => $user->fresh(),
        ]);
    }
}
