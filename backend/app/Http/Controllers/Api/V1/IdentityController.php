<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\TotpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\MfaCodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class IdentityController extends Controller
{
    public function login(LoginRequest $request, TotpService $totp): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower())->first();
        $validPassword = $user && Hash::check($request->string('password')->toString(), $user->password);

        if (! $validPassword || ! in_array($user->status, ['verified', 'active'], true) || ($user->locked_until && $user->locked_until->isFuture())) {
            $this->recordLoginFailure($user, $request);

            return response()->json(['success' => false, 'message' => 'The supplied credentials are invalid or the account is unavailable.', 'data' => null, 'meta' => ['request_id' => $request->attributes->get('request_id')]], 401);
        }

        if ($user->mfa_confirmed_at && (! $request->filled('code') || ! $totp->verify($user->mfa_secret, (string) $request->input('code')))) {
            $this->recordLoginFailure($user, $request);

            return response()->json(['success' => false, 'message' => 'The supplied credentials are invalid or the account is unavailable.', 'data' => null, 'meta' => ['request_id' => $request->attributes->get('request_id')]], 401);
        }

        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();
        $token = $user->createToken('browser-session', $user->mfa_confirmed_at ? ['*'] : ['mfa-enrollment'])->plainTextToken;
        $this->audit($request, $user, 'login', 'success');

        return response()->json([
            'success' => true,
            'message' => $user->mfa_confirmed_at ? 'Signed in.' : 'MFA enrollment is required before using the application.',
            'data' => ['access_token' => $token, 'token_type' => 'Bearer', 'mfa_enrollment_required' => ! $user->mfa_confirmed_at],
            'meta' => ['request_id' => $request->attributes->get('request_id')],
        ]);
    }

    public function startMfa(Request $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->currentAccessToken()?->abilities ?? [], [['*'], ['mfa-enrollment']], true), 403);

        $secret = $totp->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => null])->save();

        return response()->json([
            'success' => true,
            'message' => 'Scan the authenticator setup URI and confirm with a current code.',
            'data' => ['secret' => $secret, 'provisioning_uri' => $totp->provisioningUri($secret, $user->email)],
            'meta' => ['request_id' => $request->attributes->get('request_id')],
        ]);
    }

    public function confirmMfa(MfaCodeRequest $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->mfa_secret && $totp->verify($user->mfa_secret, $request->string('code')->toString()), 422, 'The MFA code is invalid.');
        $user->forceFill(['mfa_confirmed_at' => now(), 'status' => 'active'])->save();
        $request->user()->currentAccessToken()?->forceFill(['abilities' => ['*']])->save();
        $this->audit($request, $user, 'mfa.enrolled', 'success');

        return response()->json(['success' => true, 'message' => 'MFA enrollment confirmed.', 'data' => ['mfa_enabled' => true], 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit($request, $request->user(), 'logout', 'success');
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Signed out.', 'data' => null, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    private function recordLoginFailure(?User $user, Request $request): void
    {
        if ($user) {
            $count = $user->failed_login_count + 1;
            $user->forceFill(['failed_login_count' => $count, 'locked_until' => $count >= 5 ? now()->addMinutes(15) : null])->save();
        }
        $this->audit($request, $user, 'login', 'denied');
    }

    private function audit(Request $request, ?User $user, string $action, string $outcome): void
    {
        \Illuminate\Support\Facades\DB::table('iam_access_audits')->insert([
            'actor_id' => $request->user()?->id,
            'subject_user_id' => $user?->id,
            'action' => $action,
            'outcome' => $outcome,
            'ip_address' => $request->ip(),
            'request_id' => $request->attributes->get('request_id'),
            'occurred_at' => now(),
        ]);
    }
}
