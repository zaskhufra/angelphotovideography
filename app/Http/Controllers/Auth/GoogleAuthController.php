<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '' || ! filled(config('services.google.client_secret'))) {
            return $this->loginError(__('Google sign-in is not configured.'));
        }

        $state = Str::random(64);
        $states = $request->session()->get('google_oauth_states', []);
        $states = is_array($states) ? array_values(array_filter($states, 'is_string')) : [];
        $states[] = $state;
        $request->session()->put('google_oauth_states', array_slice($states, -5));

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $this->googleRedirectUri($request),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $returnedState = $request->query('state');
        $states = $request->session()->get('google_oauth_states', []);
        $matchedStateKey = null;

        if (is_array($states) && is_string($returnedState)) {
            foreach ($states as $key => $expectedState) {
                if (is_string($expectedState) && hash_equals($expectedState, $returnedState)) {
                    $matchedStateKey = $key;

                    break;
                }
            }
        }

        if ($matchedStateKey === null) {
            return $this->loginError(__('Google sign-in could not be verified. Please try again.'));
        }

        unset($states[$matchedStateKey]);

        if ($states === []) {
            $request->session()->forget('google_oauth_states');
        } else {
            $request->session()->put('google_oauth_states', array_values($states));
        }

        if ($request->filled('error')) {
            return $this->loginError(__('Google sign-in was cancelled or denied.'));
        }

        $authorizationCode = $request->string('code')->toString();

        if ($authorizationCode === '') {
            return $this->loginError(__('Google did not return an authorization code.'));
        }

        try {
            $tokenData = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $authorizationCode,
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'redirect_uri' => $this->googleRedirectUri($request),
                    'grant_type' => 'authorization_code',
                ])
                ->throw()
                ->json();

            $accessToken = is_array($tokenData) ? ($tokenData['access_token'] ?? null) : null;

            if (! is_string($accessToken) || $accessToken === '') {
                return $this->loginError(__('Google did not return an access token.'));
            }

            $googleProfile = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            return $this->loginError(__('Google sign-in is temporarily unavailable. Please try again.'));
        }

        if (! is_array($googleProfile)) {
            return $this->loginError(__('Google returned an invalid account profile.'));
        }

        $googleId = $googleProfile['sub'] ?? null;
        $email = isset($googleProfile['email']) && is_string($googleProfile['email'])
            ? Str::lower(trim($googleProfile['email']))
            : '';
        $emailIsVerified = filter_var($googleProfile['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (! is_string($googleId) || $googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $emailIsVerified) {
            return $this->loginError(__('Use a Google account with a verified email address.'));
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user === null) {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null && $user->google_id !== null && ! hash_equals($user->google_id, $googleId)) {
                return $this->loginError(__('This email is already linked to another Google account.'));
            }

            if ($user === null) {
                $request->session()->put('pending_google_registration', [
                    'google_id' => $googleId,
                    'email' => $email,
                    'name' => is_string($googleProfile['name'] ?? null) && $googleProfile['name'] !== ''
                        ? $googleProfile['name']
                        : Str::before($email, '@'),
                ]);

                return redirect()->route('google.register');
            }

            $user->google_id = $googleId;
            $user->email_verified_at ??= now();
            $user->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        $destination = $user->can('access-admin') ? 'admin.dashboard' : 'dashboard';

        return redirect()->intended(route($destination, absolute: false));
    }

    public function registration(Request $request): View|RedirectResponse
    {
        $googleIdentity = $this->pendingGoogleRegistration($request);

        if ($googleIdentity === null) {
            return $this->loginError(__('Connect a Google account before completing registration.'));
        }

        return view('auth.google-register', [
            'googleName' => $googleIdentity['name'],
            'googleEmail' => $googleIdentity['email'],
        ]);
    }

    public function completeRegistration(Request $request): RedirectResponse
    {
        $googleIdentity = $this->pendingGoogleRegistration($request);

        if ($googleIdentity === null) {
            return $this->loginError(__('Your Google registration session expired. Please connect again.'));
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['client', 'photographer'])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (User::query()
            ->where('google_id', $googleIdentity['google_id'])
            ->orWhere('email', $googleIdentity['email'])
            ->exists()) {
            $request->session()->forget('pending_google_registration');

            return $this->loginError(__('This Google account is already registered. Please sign in.'));
        }

        $user = new User;
        $user->name = $googleIdentity['name'];
        $user->email = $googleIdentity['email'];
        $user->google_id = $googleIdentity['google_id'];
        $user->email_verified_at = now();
        $user->role = $validated['role'];
        $user->password = $validated['password'];
        $user->save();

        $request->session()->forget('pending_google_registration');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * @return array{google_id: string, email: string, name: string}|null
     */
    private function pendingGoogleRegistration(Request $request): ?array
    {
        $identity = $request->session()->get('pending_google_registration');

        if (! is_array($identity)
            || ! is_string($identity['google_id'] ?? null)
            || ! is_string($identity['email'] ?? null)
            || ! is_string($identity['name'] ?? null)) {
            return null;
        }

        return [
            'google_id' => $identity['google_id'],
            'email' => $identity['email'],
            'name' => $identity['name'],
        ];
    }

    private function googleRedirectUri(Request $request): string
    {
        $configured = config('services.google.redirect');
        $requestUri = rtrim($request->getSchemeAndHttpHost().'/auth/google/callback', '/');

        if (! is_string($configured) || $configured === '') {
            return $requestUri;
        }

        $configuredUri = rtrim($configured, '/');
        $configuredHost = strtolower(parse_url($configuredUri, PHP_URL_HOST) ?? '');
        $requestHost = strtolower($request->getHost());

        if ($configuredHost !== ''
            && $requestHost !== ''
            && $configuredHost !== $requestHost
            && (str_contains($configuredHost, 'localhost') || str_contains($configuredHost, '127.0.0.1')
                || str_contains($requestHost, 'localhost') || str_contains($requestHost, '127.0.0.1'))) {
            return $requestUri;
        }

        return $configuredUri;
    }

    private function loginError(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
