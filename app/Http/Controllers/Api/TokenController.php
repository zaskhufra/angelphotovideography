<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueAdminTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    private const TOKEN_ABILITIES = [
        'appointments:read',
        'appointments:write',
        'appointments:delete',
        'token:revoke',
    ];

    public function store(IssueAdminTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::query()->where('email', Str::lower($validated['email']))->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => __('Invalid credentials.')], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => __('Only administrator accounts may receive API tokens.')], 403);
        }

        $expiresAt = now()->addDays(30);
        $token = $user->createToken($validated['device_name'], self::TOKEN_ABILITIES, $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
            'abilities' => self::TOKEN_ABILITIES,
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => __('A bearer token is required to revoke access.')], 400);
        }

        $token->delete();

        return response()->json(['message' => __('API token revoked.')]);
    }
}
